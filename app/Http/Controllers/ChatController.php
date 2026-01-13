<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LocationGrid;
use App\Models\ClimateNormal;
use App\Models\ClimateAnalysis;
use App\Models\ClimatePrediction;
use App\Models\ClimateDasPrediction;
use App\Models\ClimateDasProbability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\View;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    private $monthNamesId = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember'
    ];

    /**
     * TAHAP 1: Menentukan daftar lokasi (Geometry) berdasarkan input teks user
     */
    private function getTargetLocations($input1, $input2 = null)
    {
        // Cek apakah input1 dan input2 adalah koordinat (Lat, Lon)
        if (is_numeric($input1) && is_numeric($input2)) {
            $lat = (float) $input1;
            $lon = (float) $input2;

            // Mencari titik terdekat di LocationGrid menggunakan ST_Distance
            $results = LocationGrid::select('location', 'province', 'regency', 'kecamatan', 'latitude', 'longitude')
                ->selectRaw("ST_Distance_Sphere(location, ST_GeomFromText(CONCAT('POINT(', ?, ' ', ?, ')'))) as distance", [$lon, $lat])
                ->orderBy('distance', 'asc')
                ->limit(10)
                ->get();
        } else {
            // Logika lama (Pencarian Nama Kecamatan)
            $query = LocationGrid::select('location', 'province', 'regency', 'kecamatan', 'latitude', 'longitude')
                ->whereRaw('TRIM(LOWER(kecamatan)) = ?', [strtolower($input1)]);

            if ($input2) {
                $query->whereRaw('TRIM(LOWER(regency)) = ?', [strtolower($input2)]);
            }
            $results = $query->get();
        }

        if ($results->isEmpty())
            return null;

        return [
            'locations' => $results->pluck('location')->toArray(),
            'info' => $results->first(),
            'avg_coord' => [
                'lat' => $results->avg('latitude'),
                'lon' => $results->avg('longitude')
            ]
        ];
    }

    // === FUNGSI PEMANGGILAN DATA BERBASIS LOKASI GEOMETRY ===

    private function getNormalDataByLocation($locations, $locationInfo)
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $avg_sql = implode(', ', array_map(fn($m) => "AVG(`$m`) as `$m`", $months));

        $data = ClimateNormal::whereIn('location', $locations)
            ->selectRaw($avg_sql)
            ->first();

        if (!$data || is_null($data->jan))
            return null;

        $dataValues = [];
        foreach ($months as $month) {
            $dataValues[] = (float) $data->$month;
        }

        return [
            'province' => $locationInfo->province,
            'regency' => $locationInfo->regency,
            'kecamatan' => $locationInfo->kecamatan,
            'locationName' => ucwords(strtolower($locationInfo->kecamatan . ', ' . $locationInfo->regency . ', ' . $locationInfo->province)),
            'data' => $dataValues,
            'coords' => ['lat' => $locationInfo->latitude, 'lon' => $locationInfo->longitude]
        ];
    }

    private function getAnalysisDataByLocation($locations, $normal_bounds_lookup)
    {
        $periods = ClimateAnalysis::select('data_period')->distinct()
            ->orderBy('data_period', 'desc')->limit(3)->get()->pluck('data_period')->reverse()->values();

        if ($periods->count() < 2)
            return null;

        $labels = [];
        $data = [];
        $upper_bounds = [];
        $lower_bounds = [];

        foreach ($periods as $period) {
            $avg_ch = ClimateAnalysis::where('data_period', $period)
                ->whereIn('location', $locations)
                ->avg('ch');

            if (!is_null($avg_ch)) {
                $labels[] = $this->formatDasLabel($period);
                $data[] = round($avg_ch, 0);
                $month_num = (int) date('n', strtotime($period));
                $upper_bounds[] = $normal_bounds_lookup[$month_num]['upper'];
                $lower_bounds[] = $normal_bounds_lookup[$month_num]['lower'];
            }
        }
        return !empty($labels) ? [
            'labels' => $labels, 
            'data' => $data, 
            'upper_bounds' => $upper_bounds, 
            'lower_bounds' => $lower_bounds] : null;
    }

    private function getPredictionDataByLocation($locations, $normal_bounds_lookup)
    {
        $periods = ClimatePrediction::select('prediction_period')->distinct()
            ->orderBy('prediction_period', 'asc')->limit(6)->get()->pluck('prediction_period');

        if ($periods->isEmpty())
            return null;

        $labels = [];
        $data = [];
        $upper_bounds = [];
        $lower_bounds = [];

        foreach ($periods as $period) {
            $avg_val = ClimatePrediction::where('prediction_period', $period)
                ->whereIn('location', $locations)
                ->avg('val');

            if (!is_null($avg_val)) {
                $labels[] = $period;
                $data[] = round($avg_val, 0);
                $month_num = (int) explode('-', $period)[1];
                $upper_bounds[] = $normal_bounds_lookup[$month_num]['upper'];
                $lower_bounds[] = $normal_bounds_lookup[$month_num]['lower'];
            }
        }
        return !empty($labels) ? ['labels' => $labels, 'data' => $data, 'upper_bounds' => $upper_bounds, 'lower_bounds' => $lower_bounds] : null;
    }

    private function getDasPredictionDataByLocation($locations)
    {
        $latest = ClimateDasPrediction::orderBy('prediction_version', 'desc')->first();
        if (!$latest)
            return null;

        $periods = ClimateDasPrediction::where('prediction_version', $latest->prediction_version)
            ->distinct()->orderBy('prediction_das_period', 'asc')->limit(3)->pluck('prediction_das_period');

        $labels = [];
        $data = [];
        $upper_bounds = [];
        $lower_bounds = [];

        foreach ($periods as $period) {
            $aggregates = ClimateDasPrediction::where('prediction_das_period', $period)
                ->where('prediction_version', $latest->prediction_version)
                ->whereIn('location', $locations)
                ->selectRaw('AVG(val) as avg_val, AVG(sh) as avg_sh')
                ->first();

            if ($aggregates && !is_null($aggregates->avg_val)) {
                $labels[] = $this->formatDasLabel($period);
                $val = (float) $aggregates->avg_val;
                $sh = (float) $aggregates->avg_sh;
                $data[] = round($val, 0);

                if ($sh > 0) {
                    $lower_bounds[] = round((85 * $val) / $sh, 0);
                    $upper_bounds[] = round((115 * $val) / $sh, 0);
                } else {
                    $lower_bounds[] = null;
                    $upper_bounds[] = null;
                }
            }
        }
        return !empty($labels) ? ['labels' => $labels, 'data' => $data, 'upper_bounds' => $upper_bounds, 'lower_bounds' => $lower_bounds] : null;
    }

    private function getDasProbabilityDataByLocation($locations)
    {
        $latest = ClimateDasProbability::orderBy('prediction_version', 'desc')->first();
        if (!$latest)
            return null;

        $periods = ClimateDasProbability::where('prediction_version', $latest->prediction_version)
            ->distinct()->orderBy('prediction_das_period', 'asc')->limit(3)->pluck('prediction_das_period');

        $cols = ['b20', 'b50', 'b100', 'b150', 'a20', 'a50', 'a100', 'a150', 'a200', 'a300'];
        $avg_sql = implode(', ', array_map(fn($c) => "AVG(`$c`) as `$c`", $cols));

        $labels = [];
        $data_arrays = array_fill_keys($cols, []);

        foreach ($periods as $period) {
            $avg_data = ClimateDasProbability::where('prediction_das_period', $period)
                ->where('prediction_version', $latest->prediction_version)
                ->whereIn('location', $locations)
                ->selectRaw($avg_sql)
                ->first();

            if ($avg_data && !is_null($avg_data->b20)) {
                $labels[] = $this->formatDasLabel($period);
                foreach ($cols as $c) {
                    $data_arrays[$c][] = round($avg_data->$c, 0);
                }
            }
        }
        return !empty($labels) ? array_merge(['labels' => $labels], $data_arrays) : null;
    }

    // --- FUNGSI UTAMA API ---

    public function getClimateData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
            if ($validator->fails())
                return response()->json(['error' => $validator->errors()->first()], 400);

            $userInput = $request->input('kecamatan');
            $parts = array_map('trim', explode(',', $userInput));

            $targetData = $this->getTargetLocations($parts[0], $parts[1] ?? null);
            if (!$targetData)
                return response()->json(['error' => 'Data wilayah tidak ditemukan.'], 404);

            $locations = $targetData['locations'];
            $locationInfo = $targetData['info'];

            $normalData = $this->getNormalDataByLocation($locations, $locationInfo);
            if (!$normalData)
                return response()->json(['error' => 'Data iklim tidak tersedia.'], 404);

            $normal_bounds_lookup = [];
            foreach ($normalData['data'] as $i => $val) {
                $normal_bounds_lookup[$i + 1] = ['upper' => round($val * 1.15, 0), 'lower' => round($val * 0.85, 0)];
            }

            $analysisData = $this->getAnalysisDataByLocation($locations, $normal_bounds_lookup);
            $dasPredictionData = $this->getDasPredictionDataByLocation($locations);
            $dasProbabilityData = $this->getDasProbabilityDataByLocation($locations);
            $predictionData = $this->getPredictionDataByLocation($locations, $normal_bounds_lookup);

            return response()->json([
                'locationName' => $normalData['locationName'],
                'intro_narrative' => $this->generateIntroNarrative($normalData['locationName']),
                'normal' => [
                    '12_months' => ['labels' => ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'], 'data' => $normalData['data'], 'data_upper_bound' => array_column($normal_bounds_lookup, 'upper'), 'data_lower_bound' => array_column($normal_bounds_lookup, 'lower')],
                    '24_months' => ['labels' => array_merge(['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'], ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES']), 'data' => array_merge($normalData['data'], $normalData['data']), 'data_upper_bound' => array_merge(array_column($normal_bounds_lookup, 'upper'), array_column($normal_bounds_lookup, 'upper')), 'data_lower_bound' => array_merge(array_column($normal_bounds_lookup, 'lower'), array_column($normal_bounds_lookup, 'lower'))],
                    'narrative' => $this->generateNormalNarrative($normalData, $normalData['locationName']),
                ],
                'analysis' => $analysisData ? array_merge($analysisData, ['narrative' => $this->generateAnalysisNarrative($analysisData, $normalData['locationName'])]) : null,
                'das_prediction' => $dasPredictionData ? array_merge($dasPredictionData, ['narrative' => $this->generateDasCombinedNarrative($dasPredictionData, $dasProbabilityData, $normalData['locationName'])]) : null,
                'das_probability' => $dasProbabilityData,
                'prediction' => $predictionData ? array_merge($predictionData, ['narrative' => $this->generatePredictionNarrative($predictionData, $normalData['locationName'])]) : null,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function downloadClimateData(Request $request)
    {
        // 1. Validasi Input
        $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['error' => 'Input wilayah diperlukan.'], 400);
        }

        $userInput = $request->input('kecamatan');
        $parts = array_map('trim', explode(',', $userInput));

        // 2. Tahap Geospasial: Dapatkan list geometri lokasi (location)
        // Ini memastikan data yang didownload sama persis dengan yang ada di grafik chat
        $targetData = $this->getTargetLocations($parts[0], $parts[1] ?? null);

        if (!$targetData) {
            return response()->json(['error' => 'Data lokasi tidak ditemukan untuk download.'], 404);
        }

        $locations = $targetData['locations'];
        $locationInfo = $targetData['info'];

        // 3. Ambil Seluruh Data Berbasis Array Lokasi (Geometri)
        $normalData = $this->getNormalDataByLocation($locations, $locationInfo);

        if (!$normalData) {
            return response()->json(['error' => 'Gagal mengambil data normal untuk download.'], 404);
        }

        // Siapkan lookup batas normal bulanan (statis 85% - 115%)
        $normal_bounds_lookup = [];
        foreach ($normalData['data'] as $i => $val) {
            $normal_bounds_lookup[$i + 1] = [
                'upper' => round($val * 1.15, 2),
                'lower' => round($val * 0.85, 2)
            ];
        }

        // Ambil data lainnya menggunakan list geometri yang sama
        $analysisData = $this->getAnalysisDataByLocation($locations, $normal_bounds_lookup);
        $predictionData = $this->getPredictionDataByLocation($locations, $normal_bounds_lookup);
        $dasPredictionData = $this->getDasPredictionDataByLocation($locations);
        $dasProbabilityData = $this->getDasProbabilityDataByLocation($locations);

        // 4. Menyusun Baris Data CSV
        $dataRows = [];
        $dataRows[] = ["Provinsi: " . ucfirst($locationInfo->province)];
        $dataRows[] = ["Kabupaten/Kota: " . ucfirst($locationInfo->regency)];
        $dataRows[] = ["Kecamatan: " . ucfirst($locationInfo->kecamatan)];
        $dataRows[] = [];

        // A. Seksi Data Normal (1991-2020)
        $dataRows[] = ['DATA NORMAL'];
        $dataRows[] = ['Bulan', 'Rata-Rata CH (mm)', 'Batas Atas (mm)', 'Batas Bawah (mm)'];
        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        foreach ($normalData['data'] as $index => $value) {
            $monthNum = $index + 1;
            $dataRows[] = [
                $monthNames[$index],
                $this->formatToLocalString($value),
                $this->formatToLocalString($normal_bounds_lookup[$monthNum]['upper']),
                $this->formatToLocalString($normal_bounds_lookup[$monthNum]['lower'])
            ];
        }
        $dataRows[] = [];

        // B. Seksi Data Analisis (3 Bulan Terakhir)
        if ($analysisData) {
            $dataRows[] = ['DATA ANALISIS'];
            $dataRows[] = ['Periode', 'CH Aktual (mm)', 'Batas Atas Normal', 'Batas Bawah Normal', 'Status'];

            foreach ($analysisData['labels'] as $index => $period) {
                $value = $analysisData['data'][$index] ?? 0;
                $upper = $analysisData['upper_bounds'][$index] ?? 0;
                $lower = $analysisData['lower_bounds'][$index] ?? 0;

                $status = 'Normal';
                if ($value > $upper)
                    $status = 'Atas Normal (Lebih Basah)';
                elseif ($value < $lower)
                    $status = 'Bawah Normal (Lebih Kering)';

                $dataRows[] = [
                    $period, // Label dasarian/bulan sudah terformat
                    $this->formatToLocalString($value),
                    $this->formatToLocalString($upper),
                    $this->formatToLocalString($lower),
                    $status
                ];
            }
            $dataRows[] = [];
        }

        // C. Seksi Data Prediksi Dasarian (3 Dasarian ke Depan)
        // Batas atas/bawah di sini sudah menggunakan rumus 85*val/sh sesuai getDasPredictionDataByLocation
        if ($dasPredictionData) {
            $dataRows[] = ['DATA PREDIKSI DASARIAN'];
            $dataRows[] = ['Periode Dasarian', 'CH Prediksi (mm)', 'Batas Atas Normal', 'Batas Bawah Normal', 'Status'];
            foreach ($dasPredictionData['labels'] as $index => $periodLabel) {
                $value = $dasPredictionData['data'][$index];
                $upper = $dasPredictionData['upper_bounds'][$index];
                $lower = $dasPredictionData['lower_bounds'][$index];

                $status = 'Normal';
                if ($value > $upper)
                    $status = 'Atas Normal (Lebih Basah)';
                elseif ($value < $lower)
                    $status = 'Bawah Normal (Lebih Kering)';

                $dataRows[] = [
                    $periodLabel,
                    $this->formatToLocalString($value),
                    $this->formatToLocalString($upper),
                    $this->formatToLocalString($lower),
                    $status
                ];
            }
            $dataRows[] = [];
        }

        // D. Seksi Data Peluang Dasarian
        if ($dasProbabilityData) {
            $dataRows[] = ['DATA PELUANG DASARIAN'];
            $probColumns = ['b20', 'b50', 'b100', 'b150', 'a20', 'a50', 'a100', 'a150', 'a200', 'a300'];
            $header = array_merge(['Periode Dasarian'], $probColumns);
            $dataRows[] = $header;

            foreach ($dasProbabilityData['labels'] as $index => $periodLabel) {
                $row = [$periodLabel];
                foreach ($probColumns as $col) {
                    $row[] = $dasProbabilityData[$col][$index] ?? '0';
                }
                $dataRows[] = $row;
            }
            $dataRows[] = [];
        }

        // E. Seksi Data Prediksi Bulanan (6 Bulan ke Depan)
        if ($predictionData) {
            $dataRows[] = ['DATA PREDIKSI BULANAN'];
            $dataRows[] = ['Periode', 'CH Prediksi (mm)', 'Batas Atas Normal', 'Batas Bawah Normal', 'Status'];
            foreach ($predictionData['labels'] as $index => $period) {
                $value = $predictionData['data'][$index] ?? 0;
                $upper = $predictionData['upper_bounds'][$index] ?? 0;
                $lower = $predictionData['lower_bounds'][$index] ?? 0;

                $status = 'Normal';
                if ($value > $upper)
                    $status = 'Atas Normal (Lebih Basah)';
                elseif ($value < $lower)
                    $status = 'Bawah Normal (Lebih Kering)';

                $dataRows[] = [
                    $period,
                    $this->formatToLocalString($value),
                    $this->formatToLocalString($upper),
                    $this->formatToLocalString($lower),
                    $status
                ];
            }
            $dataRows[] = [];
        }

        $dataRows[] = ['Data ini berasal dari arsip operasional/official BMKG.'];
        $dataRows[] = ['DISCLAIMER: SEMUA KEPUTUSAN YANG DIBUAT BERDASARKAN DATA INI MENJADI TANGGUNG JAWAB PENGGUNA.'];

        // 5. Konversi ke Format CSV
        $csvContent = "\xEF\xBB\xBF"; // UTF-8 BOM agar Excel membaca karakter khusus dengan benar
        foreach ($dataRows as $row) {
            $csvContent .= implode(';', $row) . "\n";
        }

        $safeName = str_replace(' ', '_', strtolower($locationInfo->kecamatan));
        $fileName = 'Data_Iklim_' . $safeName . '_' . date('Ymd') . '.csv';

        return response($csvContent, 200)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    // --- FUNGSI FORMATTING & BANTUAN ---

    public function searchKecamatan(Request $request)
    {
        $term = $request->query('term');
        if (strlen($term) < 2)
            return response()->json([]);
        $data = LocationGrid::select('kecamatan', 'regency')->where('kecamatan', 'LIKE', $term . '%')->distinct()->limit(10)->get();
        return response()->json($data->map(fn($item) => ['value' => ucwords(strtolower($item->kecamatan)), 'display' => ucwords(strtolower($item->kecamatan . ', ' . $item->regency))]));
    }

    private function formatToLocalString($value)
    {
        return is_numeric($value) ? number_format((float) $value, 0, ',', '.') : $value;
    }

    private function formatDasLabel($period)
    {
        try {
            $p = explode('-', $period);
            return "Das {$p[2]} " . ($this->monthNamesId[(int) $p[1]] ?? '') . " {$p[0]}";
        } catch (\Exception $e) {
            return $period;
        }
    }

    private function formatMonthYear($period)
    {
        try {
            $p = explode('-', $period);
            return ($this->monthNamesId[(int) $p[1]] ?? '') . " {$p[0]}";
        } catch (\Exception $e) {
            return $period;
        }
    }

    private function formatListAnd(array $items)
    {
        if (empty($items))
            return '';
        if (count($items) === 1)
            return $items[0];
        $last = array_pop($items);
        return implode(', ', $items) . ' dan ' . $last;
    }

    private function sanitizeNarrativeText($html)
    {
        $html = str_ireplace(['<strong>', '</strong>'], ['__S__', '__SE__'], $html);
        $html = str_replace(['<', '>'], ['&lt;', '&gt;'], $html);
        return str_ireplace(['__S__', '__SE__'], ['<strong>', '</strong>'], $html);
    }

    // --- FUNGSI UNTUK NARASI INTRO ---
    private function generateIntroNarrative($locationName)
    {
        $viewData = [
            'locationName' => $locationName,
            'templateId' => rand(1, 5)
        ];
        return View::make("narratives.intro", $viewData)->render();
    }

    // --- FUNGSI UNTUK NARASI NORMAL ---
    private function generateNormalNarrative($normalData, $locationName)
    {
        if (empty($normalData['data']))
            return "";

        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $data = $normalData['data'];

        $maxRain = max($data);
        $minRain = min($data);
        $peakMonthIndex = array_search($maxRain, $data);
        $troughMonthIndex = array_search($minRain, $data);
        $peakMonth = $monthNames[$peakMonthIndex];
        $troughMonth = $monthNames[$troughMonthIndex];
        $kategoriRendahMonths = [];
        $kategoriTinggiMonths = [];
        $musimKemarauMonths = [];

        foreach ($data as $index => $val) {
            $month = $monthNames[$index];
            if ($val <= 100)
                $kategoriRendahMonths[] = $month;
            if ($val > 300)
                $kategoriTinggiMonths[] = $month;
            if ($val < 150)
                $musimKemarauMonths[] = $month;
        }

        $viewData = [
            'locationName' => $locationName,
            'peakMonth' => $peakMonth,
            'troughMonth' => $troughMonth,
            'maxRain' => round($maxRain),
            'minRain' => round($minRain),

            'kategoriTinggiMonths' => $kategoriTinggiMonths,
            'kategoriRendahMonths' => $kategoriRendahMonths,
            'musimKemarauMonths' => $musimKemarauMonths,
            'templateId' => rand(1, 5)
        ];
        $narrative = View::make("narratives.normal", $viewData)->render();

        return $this->sanitizeNarrativeText($narrative);
    }

    // --- FUNGSI UNTUK NARASI ANALYSIS ---
    private function generateAnalysisNarrative($analysisData, $locationName)
    {
        if (empty($analysisData['data']) || count($analysisData['data']) < 2)
            return "";

        $real_labels = $analysisData['labels'];
        $real_data = $analysisData['data'];
        $real_upper_bounds = $analysisData['upper_bounds'];
        $real_lower_bounds = $analysisData['lower_bounds'];
        $num_real_points = count($real_data);
        $above_normal_months = [];
        $below_normal_months = [];
        $normal_months = [];

        foreach ($real_data as $index => $value) {
            $month_number = (int) date('n', strtotime($real_labels[$index]));
            $month_name = $this->monthNamesId[$month_number] ?? $real_labels[$index];
            $month_info = "{$month_name} (" . round($value) . " mm)";

            if ($value > $real_upper_bounds[$index]) {
                $above_normal_months[] = $month_info;
            } elseif ($value < $real_lower_bounds[$index]) {
                $below_normal_months[] = $month_info;
            } else {
                $normal_months[] = $month_info;
            }
        }

        $summary_parts = [];
        if (!empty($normal_months))
            $summary_parts[] = "kondisi <strong>normal</strong> teramati pada bulan " . $this->formatListAnd($normal_months);
        if (!empty($above_normal_months))
            $summary_parts[] = "kondisi <strong>di atas normal (lebih basah)</strong> terjadi pada bulan " . $this->formatListAnd($above_normal_months);
        if (!empty($below_normal_months))
            $summary_parts[] = "kondisi <strong>di bawah normal (lebih kering)</strong> terlihat pada bulan " . $this->formatListAnd($below_normal_months);

        $summary = !empty($summary_parts) ? "Dari data yang telah divisualisasikan, terlihat bahwa " . implode(', sedangkan ', $summary_parts) . ". " : "";
        $conclusion = "";
        $last_value = end($real_data);
        $last_upper_bound = end($real_upper_bounds);
        $last_lower_bound = end($real_lower_bounds);

        if ($last_value > $last_upper_bound) {
            $conclusion = "Pola ini mengindikasikan bahwa wilayah <strong>{$locationName}</strong> baru-baru ini memasuki periode yang cenderung <strong>lebih basah</strong> dari biasanya.";
        } elseif ($last_value < $last_lower_bound) {
            $conclusion = "Pola ini menunjukkan adanya tren <strong>kondisi yang lebih kering</strong> dari biasanya di wilayah <strong>{$locationName}</strong>.";
        } else {
            if (count($summary_parts) > 1) {
                $conclusion = "Secara keseluruhan, data menunjukkan adanya <strong>fluktuasi kondisi curah hujan</strong> yang signifikan dalam beberapa waktu terakhir.";
            }
        }

        $viewData = [
            'locationName' => $locationName,
            'num_real_points' => $num_real_points,
            'summary' => $summary,
            'conclusion' => $conclusion,
            'templateId' => rand(1, 5)
        ];
        return View::make("narratives.analysis", $viewData)->render();
    }

    // --- FUNGSI UNTUK NARASI PREDICTION ---
    private function generatePredictionNarrative($predictionData, $locationName)
    {
        if (empty($predictionData['data']))
            return "";

        $data = $predictionData['data'];
        $labels = $predictionData['labels'];
        $upper_bounds = $predictionData['upper_bounds'];
        $lower_bounds = $predictionData['lower_bounds'];
        $count = count($data);
        if ($count == 0)
            return "";

        $month_start_num = (int) date('n', strtotime($labels[0]));
        $month_end_num = (int) date('n', strtotime($labels[$count - 1]));
        $year_start = date('Y', strtotime($labels[0]));
        $year_end = date('Y', strtotime($labels[$count - 1]));

        $startMonth = ($this->monthNamesId[$month_start_num] ?? 'Bulan') . " {$year_start}";
        $endMonth = ($this->monthNamesId[$month_end_num] ?? 'Bulan') . " {$year_end}";

        $details = [];
        for ($i = 0; $i < $count; $i++) {
            $value = $data[$i];
            $upper = $upper_bounds[$i];
            $lower = $lower_bounds[$i];
            $status = "";

            if ($value === null || $upper === null || $lower === null) {
                $status = "(data normal tidak tersedia)";
            } elseif ($value > $upper) {
                $status = "(atas normal)";
            } elseif ($value < $lower) {
                $status = "(bawah normal)";
            } else {
                $status = "(normal)";
            }
            $details[] = "<strong>" . round($value) . " mm/bulan</strong> {$status}";
        }

        $viewData = [
            'locationName' => $locationName,
            'startMonth' => $startMonth,
            'endMonth' => $endMonth,
            'listDetails' => implode(', ', $details) . ".",
            'templateId' => rand(1, 5)
        ];
        return View::make("narratives.prediction", $viewData)->render();
    }

    // --- FUNGSI BARU UNTUK NARASI GABUNGAN DASARIAN ---
    private function generateDasCombinedNarrative($dasPredictionData, $dasProbabilityData, $locationName)
    {
        $labels = $dasPredictionData['labels'];
        $data = $dasPredictionData['data'];
        $upper_bounds = $dasPredictionData['upper_bounds'];
        $lower_bounds = $dasPredictionData['lower_bounds'];
        $count = count($labels);
        if ($count == 0)
            return "";

        $detailsPred = [];
        for ($i = 0; $i < $count; $i++) {
            $value = $data[$i];
            $label = $labels[$i];
            $upper = $upper_bounds[$i];
            $lower = $lower_bounds[$i];
            $status = "";

            if ($value === null || $upper === null || $lower === null) {
                $status = "(data normal tidak tersedia)";
            } elseif ($value > $upper) {
                $status = "(atas normal)";
            } elseif ($value < $lower) {
                $status = "(bawah normal)";
            } else {
                $status = "(normal)";
            }
            $detailsPred[] = "sebesar <strong>" . round($value) . " mm/dasarian</strong> {$status} pada <strong>{$label}</strong>";
        }
        $listDetailsPred = ($count > 0) ? implode(', ', $detailsPred) . "." : "";

        $listDetailsProb = null;
        $defaultProbThreshold = null;

        if ($dasProbabilityData && !empty($dasProbabilityData['labels'])) {
            $defaultProbKey = 'a20';
            $defaultProbThreshold = "lebih dari 20 mm";

            $prob_labels = $dasProbabilityData['labels'];
            $prob_data = $dasProbabilityData[$defaultProbKey];
            $prob_count = count($prob_labels);
            $prob_details_parts = [];

            if ($prob_count > 0) {
                for ($i = 0; $i < $prob_count; $i++) {
                    if (isset($prob_data[$i]) && isset($prob_labels[$i])) {
                        $prob_details_parts[] = "<strong>" . round($prob_data[$i]) . "%</strong> pada <strong>" . $prob_labels[$i] . "</strong>";
                    }
                }

                $listDetailsProb = $this->formatListAnd($prob_details_parts);
            }
        }

        $viewData = [
            'locationName' => $locationName,
            'listDetailsPred' => $listDetailsPred,
            'listDetailsProb' => $listDetailsProb,
            'defaultProbThreshold' => $defaultProbThreshold,
            'templateId' => rand(1, 5)
        ];

        return View::make("narratives.das_combined", $viewData)->render();
    }

}