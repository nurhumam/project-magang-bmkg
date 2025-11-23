<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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

    // === FUNGSI UNTUK PEMANGGILAN DATA START ===
    // --- FUNGSI UNTUK MENDAPATKAN DATA NORMAL ---
    private function getNormalData($lat, $lon, $kecamatanInput, $regencyInput = null)
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $avg_months_sql = implode(', ', array_map(fn($m) => "AVG(`$m`) as `$m`", $months));

        // --- Pencarian berdasarkan koordinat ---
        if ($lat && $lon) {
            $point_string = "ST_GeomFromText('POINT($lon $lat)')";

            $data = ClimateNormal::select('*')
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();
        } else {
            $kecamatanLower = strtolower($kecamatanInput);

            $query = ClimateNormal::selectRaw(
                "MAX(province) as province, MAX(regency) as regency, ? as kecamatan, AVG(latitude) as latitude, AVG(longitude) as longitude, $avg_months_sql",
                [$kecamatanInput]
            )
                ->whereRaw('LOWER(kecamatan) = ?', [$kecamatanLower]);

            if ($regencyInput) {
                $query->whereRaw('LOWER(regency) = ?', [strtolower($regencyInput)]);
            }

            $data = $query->first();

        }

        // Validasi akhir jika data tidak ditemukan sama sekali
        if (!$data || is_null($data->latitude)) {
            return null;
        }

        $dataValues = [];
        foreach ($months as $month) {
            $dataValues[] = (float) $data->$month;
        }

        return [
            'province' => $data->province,
            'regency' => $data->regency,
            'kecamatan' => $data->kecamatan,
            'locationName' => ucwords(strtolower($data->kecamatan . ', ' . $data->regency . ', ' . $data->province)),
            'data' => $dataValues,
            'coords' => ['lat' => $data->latitude, 'lon' => $data->longitude]
        ];
    }

    // --- FUNGSI UNTUK MENDAPATKAN DATA ANALISIS ---
    private function getAnalysisData($lat, $lon, $normal_bounds_lookup)
    {
        $periods = ClimateAnalysis::select('data_period')->distinct()
            ->orderBy('data_period', 'desc')->limit(3)->get()->pluck('data_period')->reverse()->values();

        if ($periods->count() < 2)
            return null;

        $labels = [];
        $data = [];
        $upper_bounds = [];
        $lower_bounds = [];

        $point_string = "ST_GeomFromText('POINT($lon $lat)')";

        foreach ($periods as $period) {

            $nearest = ClimateAnalysis::select('ch')
                ->where('data_period', $period)
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();

            if ($nearest) {
                $labels[] = $period;
                $data[] = round($nearest->ch, 2);

                $month_number = (int) date('n', strtotime($period));
                $upper_bounds[] = $normal_bounds_lookup[$month_number]['upper'];
                $lower_bounds[] = $normal_bounds_lookup[$month_number]['lower'];
            }
        }
        
        return count($labels) >= 2 ? [
            'labels' => $labels,
            'data' => $data,
            'upper_bounds' => $upper_bounds,
            'lower_bounds' => $lower_bounds
        ] : null;
    }

    // --- FUNGSI UNTUK MENDAPATKAN DATA PREDIKSI ---
    private function getPredictionData($lat, $lon, $normal_bounds_lookup)
    {
        $periods = ClimatePrediction::select('prediction_period')->distinct()
            ->orderBy('prediction_period', 'asc')->limit(6)->get()->pluck('prediction_period');

        if ($periods->isEmpty())
            return null;

        $labels = [];
        $data = [];
        $upper_bounds = [];
        $lower_bounds = [];

        $point_string = "ST_GeomFromText('POINT($lon $lat)')";

        foreach ($periods as $period) {

            $nearest = ClimatePrediction::select('val')
                ->where('prediction_period', $period)
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();

            if ($nearest) {
                $labels[] = $period;
                $data[] = round($nearest->val, 2);

                try {
                    $parts = explode('-', $period);
                    $month_number = (int) $parts[1];
                    $upper_bounds[] = $normal_bounds_lookup[$month_number]['upper'];
                    $lower_bounds[] = $normal_bounds_lookup[$month_number]['lower'];
                } catch (\Exception $e) {
                    $upper_bounds[] = null;
                    $lower_bounds[] = null;
                }
            }
        }
        return !empty($labels) ? [
            'labels' => $labels,
            'data' => $data,
            'upper_bounds' => $upper_bounds,
            'lower_bounds' => $lower_bounds
        ] : null;
    }

    // --- FUNGSI UNTUK MENDAPATKAN DATA PREDIKSI DASARIAN ---
    private function getDasPredictionData($lat, $lon, $normal_bounds_lookup)
    {
        $latest_version = ClimateDasPrediction::select('prediction_version')
            ->distinct()
            ->orderBy('prediction_version', 'desc')
            ->first();

        if (!$latest_version)
            return null; 

        $today = now();
        $day = $today->day;
        $month = $today->month;
        $year = $today->year;

        if ($day <= 10) {
            $current_das_string = "$year-$month-1";
        } elseif ($day <= 20) {
            $current_das_string = "$year-$month-2";
        } else {
            $current_das_string = "$year-$month-3";
        }

        $periods = ClimateDasPrediction::select('prediction_das_period')
            ->where('prediction_version', $latest_version->prediction_version)
            ->where('prediction_das_period', '>', $current_das_string)
            ->distinct()
            ->orderBy('prediction_das_period', 'asc')
            ->limit(3)
            ->get()->pluck('prediction_das_period');

        if ($periods->isEmpty())
            return null;

        $labels = [];
        $data = [];
        $upper_bounds = [];
        $lower_bounds = [];
        $point_string = "ST_GeomFromText('POINT($lon $lat)')";

        foreach ($periods as $period) {
            $nearest = ClimateDasPrediction::select('val')
                ->where('prediction_version', $latest_version->prediction_version)
                ->where('prediction_das_period', $period)
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();

            if ($nearest) {
                $labels[] = $this->formatDasLabel($period);
                $data[] = round($nearest->val, 2);

                try {
                    $parts = explode('-', $period);
                    $month_number = (int) $parts[1];
                    $upper_bounds[] = $normal_bounds_lookup[$month_number]['upper'];
                    $lower_bounds[] = $normal_bounds_lookup[$month_number]['lower'];
                } catch (\Exception $e) {
                    $upper_bounds[] = null;
                    $lower_bounds[] = null;
                }
            }
        }
        return !empty($labels) ? [
            'labels' => $labels,
            'data' => $data,
            'upper_bounds' => $upper_bounds,
            'lower_bounds' => $lower_bounds
        ] : null;
    }

    // --- FUNGSI BARU UNTUK MENGAMBIL DATA PELUANG DASARIAN ---
    private function getDasProbabilityData($lat, $lon)
    {
        $latest_version = ClimateDasProbability::select('prediction_version')
            ->distinct()
            ->orderBy('prediction_version', 'desc')
            ->first();

        if (!$latest_version)
            return null;

        $today = now();
        $day = $today->day;
        $month = $today->month;
        $year = $today->year;

        if ($day <= 10) {
            $current_das_string = "$year-$month-1";
        } elseif ($day <= 20) {
            $current_das_string = "$year-$month-2";
        } else {
            $current_das_string = "$year-$month-3";
        }

        // Ambil periode dasarian yang tersedia setelah periode saat ini
        $periods = ClimateDasProbability::select('prediction_das_period')
            ->where('prediction_version', $latest_version->prediction_version)
            ->where('prediction_das_period', '>', $current_das_string)
            ->distinct()
            ->orderBy('prediction_das_period', 'asc')
            ->limit(3)
            ->get()->pluck('prediction_das_period');

        if ($periods->isEmpty())
            return null;

        $prob_columns = [
            'b20',
            'b50',
            'b100',
            'b150',
            'a20',
            'a50',
            'a100',
            'a150',
            'a200',
            'a300'
        ];

        $labels = [];
        $data_arrays = array_fill_keys($prob_columns, []);
        $point_string = "ST_GeomFromText('POINT($lon $lat)')";

        // Loop melalui setiap periode dasarian
        foreach ($periods as $period) {
            $nearest = ClimateDasProbability::select($prob_columns)
                ->where('prediction_version', $latest_version->prediction_version)
                ->where('prediction_das_period', $period)
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();

            if ($nearest) {
                $labels[] = $this->formatDasLabel($period);

                foreach ($prob_columns as $col) {
                    $data_arrays[$col][] = round($nearest->$col, 2);
                }
            }
        }
        return !empty($labels) ? array_merge(['labels' => $labels], $data_arrays) : null;
    }
    // === FUNGSI UNTUK PEMANGGILAN DATA END ===


    // === FUNGSI BANTUAN LAINNYA START ===
    // Format label dasarian menjadi "Das X Bulan Tahun" dalam Bahasa Indonesia
    private function formatDasLabel($period_string)
    {
        try {
            $parts = explode('-', $period_string);
            $year = $parts[0];
            $month_number = (int) $parts[1];
            $das = $parts[2];
            $monthNameId = $this->monthNamesId[$month_number] ?? '';
            return "Das $das {$monthNameId} $year";
        } catch (\Exception $e) {
            return $period_string;
        }
    }

    // Fungsi untuk menggabungkan array menjadi string dengan "dan" sebelum item terakhir
    private function formatListAnd(array $items)
    {
        if (empty($items)) {
            return '';
        }

        $count = count($items);

        if ($count === 1) {
            return $items[0];
        }

        if ($count === 2) {
            return implode(' dan ', $items);
        }

        $lastItem = array_pop($items);
        return implode(', ', $items) . ', dan ' . $lastItem;
    }
    // === FUNGSI BANTUAN LAINNYA END ===


    // === FUNGSI UNTUK MEMANGGIL NARASI START ===
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
            // KIRIMKAN ARRAY MENTAH
            'kategoriTinggiMonths' => $kategoriTinggiMonths,
            'kategoriRendahMonths' => $kategoriRendahMonths,
            'musimKemarauMonths' => $musimKemarauMonths,
            'templateId' => rand(1, 5)
        ];
        return View::make("narratives.normal", $viewData)->render();
    }

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
            // Ambil nama bulan dalam Bahasa Indonesia
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

    private function generateDasCombinedNarrative($dasPredictionData, $dasProbabilityData, $locationName)
    {
        // Bagian 1: Siapkan Data Prediksi (dari fungsi lama)
        // Kita harus tetap menjalankan ini, karena narasi gabungan memerlukannya
        $labels = $dasPredictionData['labels'];
        $data = $dasPredictionData['data'];
        $upper_bounds = $dasPredictionData['upper_bounds'];
        $lower_bounds = $dasPredictionData['lower_bounds'];
        $count = count($labels);
        if ($count == 0)
            return ""; // Harus ada data prediksi

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


        // Bagian 2: Siapkan Data Peluang (Logika Dinamis Baru)
        $listDetailsProb = null;
        $defaultProbThreshold = null;

        if ($dasProbabilityData && !empty($dasProbabilityData['labels'])) {
            // Kita pilih satu threshold default untuk ditampilkan di narasi, misal 'a20'
            $defaultProbKey = 'a20';
            $defaultProbThreshold = "lebih dari 20 mm"; // Teks deskripsi untuk 'a20'

            $prob_labels = $dasProbabilityData['labels'];
            $prob_data = $dasProbabilityData[$defaultProbKey];
            $prob_count = count($prob_labels);
            $prob_details_parts = [];

            if ($prob_count > 0) {
                for ($i = 0; $i < $prob_count; $i++) {
                    // Cek jika data ada di index tsb
                    if (isset($prob_data[$i]) && isset($prob_labels[$i])) {
                        $prob_details_parts[] = "<strong>" . round($prob_data[$i]) . "%</strong> pada <strong>" . $prob_labels[$i] . "</strong>";
                    }
                }

                // Logika "Smart Implode" untuk menggabungkan dengan "serta"
                $listDetailsProb = $this->formatListAnd($prob_details_parts);
            }
        }

        // Bagian 3: Kirim semua data ke file Blade baru
        $viewData = [
            'locationName' => $locationName,
            'listDetailsPred' => $listDetailsPred,      // Data untuk narasi prediksi
            'listDetailsProb' => $listDetailsProb,      // Data untuk narasi peluang (bisa null)
            'defaultProbThreshold' => $defaultProbThreshold, // Deskripsi peluang (bisa null)
            'templateId' => rand(1, 5)
        ];

        // Panggil file blade BARU
        return View::make("narratives.das_combined", $viewData)->render();
    }

    // --- FUNGSI UTAMA (DIMODIFIKASI) ---
    public function getClimateData(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()->first()], 400);
            }

            $userInput = $request->input('kecamatan');
            $targetLat = null;
            $targetLon = null;
            $locationName = $userInput;

            if (preg_match('/^\(?\s*([-]?\d{1,3}(?:\.\d+)?)\s*,\s*([-]?\d{1,3}(?:\.\d+)?)\s*\)?$/', $userInput, $matches)) {
                $lat = $matches[1];
                $lon = $matches[2];
                $normalData = $this->getNormalData($lat, $lon, null, null);
            } else {
                $parts = array_map('trim', explode(',', $userInput));
                $kecamatan = $parts[0];
                $regency = $parts[1] ?? null;
                $normalData = $this->getNormalData(null, null, $kecamatan, $regency);
            }

            if (!$normalData) {
                return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan. Pastikan nama kecamatan atau format koordinat (lat, lon) benar.'], 404);
            }

            $targetLat = $normalData['coords']['lat'];
            $targetLon = $normalData['coords']['lon'];
            $locationName = $normalData['locationName'];

            $normal_upper_bounds = array_map(fn($val) => round($val * 1.15, 2), $normalData['data']);
            $normal_lower_bounds = array_map(fn($val) => round($val * 0.85, 2), $normalData['data']);
            $normal_bounds_lookup = [];
            for ($i = 0; $i < 12; $i++) {
                $normal_bounds_lookup[$i + 1] = [
                    'upper' => $normal_upper_bounds[$i],
                    'lower' => $normal_lower_bounds[$i]
                ];
            }

            // --- 4. PANGGIL SEMUA FUNGSI PENGAMBIL DATA ---
            $analysisData = $this->getAnalysisData($targetLat, $targetLon, $normal_bounds_lookup);
            $dasPredictionData = $this->getDasPredictionData($targetLat, $targetLon, $normal_bounds_lookup);
            // Panggil fungsi data peluang baru
            $dasProbabilityData = $this->getDasProbabilityData($targetLat, $targetLon);
            $predictionData = $this->getPredictionData($targetLat, $targetLon, $normal_bounds_lookup);

            // --- 5. PANGGIL SEMUA FUNGSI NARASI ---
            $introNarrative = $this->generateIntroNarrative($locationName);
            $normalNarrative = $this->generateNormalNarrative($normalData, $locationName);
            $analysisNarrative = $analysisData ? $this->generateAnalysisNarrative($analysisData, $locationName) : null;
            $dasCombinedNarrative = null;
            if ($dasPredictionData) {
                $dasCombinedNarrative = $this->generateDasCombinedNarrative(
                    $dasPredictionData,
                    $dasProbabilityData, // Kirim data probabilitas (bisa jadi null)
                    $locationName
                );
            }
            $predictionNarrative = $predictionData ? $this->generatePredictionNarrative($predictionData, $locationName) : null;

            $labels_12_months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOV', 'DES'];
            $data_12_months = $normalData['data'];
            $upper_12_months = $normal_upper_bounds;
            $lower_12_months = $normal_lower_bounds;

            $labels_24_months = array_merge($labels_12_months, $labels_12_months);
            $data_24_months = array_merge($data_12_months, $data_12_months);
            $upper_24_months = array_merge($upper_12_months, $upper_12_months);
            $lower_24_months = array_merge($lower_12_months, $lower_12_months);

            // --- 6. KIRIM SEMUA DATA KE JSON RESPONSE ---
            return response()->json([
                'locationName' => $locationName,
                'intro_narrative' => $introNarrative,
                'normal' => [
                    '12_months' => [
                        'labels' => $labels_12_months,
                        'data' => $data_12_months,
                        'data_upper_bound' => $upper_12_months,
                        'data_lower_bound' => $lower_12_months,
                    ],
                    '24_months' => [
                        'labels' => $labels_24_months,
                        'data' => $data_24_months,
                        'data_upper_bound' => $upper_24_months,
                        'data_lower_bound' => $lower_24_months,
                    ],
                    'narrative' => $normalNarrative,
                ],
                'analysis' => $analysisData ? array_merge($analysisData, ['narrative' => $analysisNarrative]) : null,
                'das_prediction' => $dasPredictionData
                    ? array_merge($dasPredictionData, ['narrative' => $dasCombinedNarrative])
                    : null,
                // Atur 'das_probability.narrative' ke null agar tidak tampil dua kali
                'das_probability' => $dasProbabilityData
                    ? array_merge($dasProbabilityData, ['narrative' => null])
                    : null,
                'prediction' => $predictionData ? array_merge($predictionData, ['narrative' => $predictionNarrative]) : null,
            ]);


        } catch (\Exception $e) {
            // Opsional: Log error ke Laravel logs
            \Illuminate\Support\Facades\Log::error('Climate Data Error: ' . $e->getMessage() . ' on line ' . $e->getLine());

            // Kembalikan response error dengan pesan yang lebih detail
            return response()->json([
                // Pesan ini hanya untuk debugging, Anda bisa ganti dengan pesan generik
                'error' => 'Terjadi kesalahan internal: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')',
                'debug' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }

    }

    public function searchKecamatan(Request $request)
    {
        $term = $request->query('term');
        if (empty($term) || strlen($term) < 2) {
            return response()->json([]);
        }

        $data = ClimateNormal::select('kecamatan', 'regency')
            ->where('kecamatan', 'LIKE', $term . '%')
            ->distinct()
            ->limit(10)
            ->get();

        $results = $data->map(function ($item) {
            $kecamatan = ucwords(strtolower($item->kecamatan));
            $regency = ucwords(strtolower($item->regency));

            return [
                'value' => $kecamatan,
                'display' => $kecamatan . ', ' . $regency
            ];
        });

        return response()->json($results);
    }

    private function formatToLocalString($value)
    {
        if (is_numeric($value)) {
            // Kita ingin membatasi hingga 2 desimal (sesuai data Anda) dan
            // menggunakan koma (,) sebagai desimal dan titik (.) sebagai ribuan.
            return number_format((float)$value, 2, ',', '.');
        }
        return $value;
    }
    public function downloadClimateData(Request $request)
    {
        // 1. Validasi Input (Sama seperti getClimateData)
        $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['error' => 'Input wilayah diperlukan.'], 400);
        }

        $userInput = $request->input('kecamatan');
        $targetLat = null;
        $targetLon = null;
        $locationName = $userInput;

        // 2. Cari Data Normal untuk mendapatkan Koordinat
        if (preg_match('/^\(?\s*([-]?\d{1,3}(?:\.\d+)?)\s*,\s*([-]?\d{1,3}(?:\.\d+)?)\s*\)?$/', $userInput, $matches)) {
            $lat = $matches[1];
            $lon = $matches[2];
            $normalData = $this->getNormalData($lat, $lon, null, null);
        } else {
            $parts = array_map('trim', explode(',', $userInput));
            $kecamatan = $parts[0];
            $regency = $parts[1] ?? null;
            $normalData = $this->getNormalData(null, null, $kecamatan, $regency);
        }

        if (!$normalData) {
            return response()->json(['error' => 'Data lokasi tidak ditemukan untuk download.'], 404);
        }

        $targetLat = $normalData['coords']['lat'];
        $targetLon = $normalData['coords']['lon'];
        $locationName = $normalData['locationName'];
        $provinces = $normalData['province'];
        $regencys = $normalData['regency'];
        $kecamatans = $normalData['kecamatan'];
        $province = ucfirst($provinces);
        $regency = ucfirst($regencys);
        $kecamatan = ucfirst($kecamatans);

        // 3. Tentukan batas normal untuk digunakan di fungsi data lainnya
        $normal_data = $normalData['data']; // Data 12 bulan
        $normal_bounds_lookup = [];
        for ($i = 0; $i < 12; $i++) {
            $normal_bounds_lookup[$i + 1] = [
                'upper' => round($normal_data[$i] * 1.15, 2),
                'lower' => round($normal_data[$i] * 0.85, 2)
            ];
        }

        // 4. Ambil semua data
        $analysisData = $this->getAnalysisData($targetLat, $targetLon, $normal_bounds_lookup);
        $predictionData = $this->getPredictionData($targetLat, $targetLon, $normal_bounds_lookup);
        $dasPredictionData = $this->getDasPredictionData($targetLat, $targetLon, $normal_bounds_lookup);
        $dasProbabilityData = $this->getDasProbabilityData($targetLat, $targetLon);

        // 5. Konsolidasikan data ke dalam format datar (Flattening Data)
        $dataRows = [];

        // Header Metadata
        // $dataRows[] = ['Tipe Data', 'Periode', 'Nilai (mm/das/bulan)', 'Keterangan'];
        $dataRows[] = ["Provinsi: {$province}", "Kabupaten/Kota: {$regency}", "Kecamatan: {$kecamatan}", "Latitude: {$this->formatToLocalString($targetLat)}", "Longitude: {$this->formatToLocalString($targetLon)}"];
        // $dataRows[] = ["Lokasi: {$locationName} (Lat: {$targetLat}, Lon: {$targetLon})"];
        $dataRows[] = [];

        // A. Data Normal (1991-2020)
        $dataRows[] = ['DATA NORMAL'];
        $dataRows[] = ['Bulan', 'Rata-Rata CH (mm)', 'Batas Atas (mm)', 'Batas Bawah (mm)'];
        $monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        
        foreach ($normalData['data'] as $index => $value) {
            $month = $monthNames[$index];
            $upper = $normal_bounds_lookup[$index + 1]['upper'];
            $lower = $normal_bounds_lookup[$index + 1]['lower'];
            $dataRows[] = [$month, $this->formatToLocalString($value), $this->formatToLocalString($upper), $this->formatToLocalString($lower)];
        }
        $dataRows[] = [];

        // B. Data Analisis (3 Bulan Terakhir)
        if ($analysisData) {
            $dataRows[] = ['DATA ANALISIS'];
            $dataRows[] = ['Periode', 'CH Aktual (mm)', 'Batas Atas Normal', 'Batas Bawah Normal', 'Status'];
            foreach ($analysisData['labels'] as $index => $period) {
                $value = $analysisData['data'][$index];
                $upper = $analysisData['upper_bounds'][$index];
                $lower = $analysisData['lower_bounds'][$index];
                $status = 'Normal';
                if ($value > $upper) $status = 'Atas Normal (Lebih Basah)';
                elseif ($value < $lower) $status = 'Bawah Normal (Lebih Kering)';
                $dataRows[] = [$period, $this->formatToLocalString($value), $this->formatToLocalString($upper), $this->formatToLocalString($lower), $status];
            }
            $dataRows[] = [];
        }

        // C. Data Prediksi Bulanan (6 Bulan ke Depan)
        if ($predictionData) {
            $dataRows[] = ['DATA PREDIKSI BULANAN'];
            $dataRows[] = ['Periode', 'CH Prediksi (mm)', 'Batas Atas Normal', 'Batas Bawah Normal', 'Status'];
            foreach ($predictionData['labels'] as $index => $period) {
                $value = $predictionData['data'][$index];
                $upper = $predictionData['upper_bounds'][$index];
                $lower = $predictionData['lower_bounds'][$index];
                $status = 'Normal';
                if ($value > $upper) $status = 'Atas Normal (Lebih Basah)';
                elseif ($value < $lower) $status = 'Bawah Normal (Lebih Kering)';
                $dataRows[] = [$period, $this->formatToLocalString($value), $this->formatToLocalString($upper), $this->formatToLocalString($lower), $status];
            }
            $dataRows[] = [];
        }

        // D. Data Prediksi Dasarian (3 Dasarian ke Depan)
        if ($dasPredictionData) {
            $dataRows[] = ['DATA PREDIKSI DASARIAN'];
            $dataRows[] = ['Periode Dasarian', 'CH Prediksi (mm)', 'Batas Atas Normal', 'Batas Bawah Normal', 'Status'];
            foreach ($dasPredictionData['labels'] as $index => $periodLabel) {
                $value = $dasPredictionData['data'][$index];
                $upper = $dasPredictionData['upper_bounds'][$index];
                $lower = $dasPredictionData['lower_bounds'][$index];
                $status = 'Normal';
                if ($value > $upper) $status = 'Atas Normal (Lebih Basah)';
                elseif ($value < $lower) $status = 'Bawah Normal (Lebih Kering)';
                $dataRows[] = [$periodLabel, $this->formatToLocalString($value), $this->formatToLocalString($upper), $this->formatToLocalString($lower), $status];
            }
            $dataRows[] = [];
        }

        // E. Data Peluang Dasarian (3 Dasarian ke Depan)
        if ($dasProbabilityData) {
            $dataRows[] = ['DATA PELUANG DASARIAN'];
            $probColumns = array_keys(array_diff_key($dasProbabilityData, ['labels' => '', 'prediction_version' => '']));
            $header = array_merge(['Periode Dasarian'], $probColumns);
            $dataRows[] = $header;

            foreach ($dasProbabilityData['labels'] as $index => $periodLabel) {
                $row = [$periodLabel];
                foreach ($probColumns as $col) {
                    $row[] = $dasProbabilityData[$col][$index] ?? 'N/A';
                }
                $dataRows[] = $row;
            }
        }

        // 6. Konversi ke CSV (dengan batas titik koma ';')
        $csvContent = '';
        foreach ($dataRows as $row) {
            $csvContent .= implode(';', $row) . "\n";
        }

        $fileName = 'Data_Iklim_' . preg_replace('/[^A-Za-z0-9\_]/', '_', $locationName) . '_Ver_' . date('Y.m.d') . '.csv';

        // 7. Mengembalikan Response Download
        return response($csvContent, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');

    }
}