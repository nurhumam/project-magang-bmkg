<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateNormal;
use App\Models\ClimateAnalysis;
use App\Models\ClimatePrediction;
use App\Models\ClimateDasPrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    // --- FUNGSI GETNORMALDATA (DIMODIFIKASI) ---
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
        // (Ini akan menangkap jika kecamatan tidak ditemukan)
        if (!$data || is_null($data->latitude)) {
            return null;
        }

        $dataValues = [];
        foreach ($months as $month) {
            $dataValues[] = (float) $data->$month;
        }

        return [
            // --- Tampilan Nama Lokasi Diperbaiki (Kecamatan, Kabupaten) ---
            'locationName' => ucwords(strtolower($data->kecamatan . ', ' . $data->regency)),
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
    private function getPredictionData($lat, $lon)
    {
        $periods = ClimatePrediction::select('prediction_period')->distinct()
            ->orderBy('prediction_period', 'asc')->limit(6)->get()->pluck('prediction_period');

        if ($periods->isEmpty())
            return null;

        $labels = [];
        $data = [];

        $point_string = "ST_GeomFromText('POINT($lon $lat)')";

        foreach ($periods as $period) {

            $nearest = ClimatePrediction::select('val')
                ->where('prediction_period', $period)
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();

            if ($nearest) {
                $labels[] = $period;
                $data[] = round($nearest->val, 2);
            }
        }
        return !empty($labels) ? ['labels' => $labels, 'data' => $data] : null;
    }

    private function getDasPredictionData($lat, $lon)
    {
        // 1. Cari versi prediksi terbaru yang ada di database
        $latest_version = ClimateDasPrediction::select('prediction_version')
                            ->distinct()
                            ->orderBy('prediction_version', 'desc')
                            ->first();

        if (!$latest_version) return null; // Tidak ada data

        // 2. Tentukan string dasarian hari ini (untuk perbandingan)
        $today = now();
        $day = $today->day;
        $month = $today->month;
        $year = $today->year;

        if ($day <= 10) { // Das 1
            $current_das_string = "$year-$month-1";
        } elseif ($day <= 20) { // Das 2
            $current_das_string = "$year-$month-2";
        } else { // Das 3
            $current_das_string = "$year-$month-3";
        }

        // 3. Ambil 3 periode dasarian BERIKUTNYA (>) dari hari ini,
        //    sesuai dengan versi terbaru
        $periods = ClimateDasPrediction::select('prediction_das_period')
            ->where('prediction_version', $latest_version->prediction_version)
            ->where('prediction_das_period', '>', $current_das_string) // Logika kunci: > (setelah hari ini)
            ->distinct()
            ->orderBy('prediction_das_period', 'asc')
            ->limit(3)
            ->get()->pluck('prediction_das_period');

        if ($periods->isEmpty()) return null; // Tidak ada data prediksi baru

        $labels = [];
        $data = [];
        $point_string = "ST_GeomFromText('POINT($lon $lat)')";

        foreach ($periods as $period) {
            $nearest = ClimateDasPrediction::select('val')
                ->where('prediction_version', $latest_version->prediction_version)
                ->where('prediction_das_period', $period)
                ->orderByRaw("ST_Distance_Sphere(location, $point_string)")
                ->first();

            if ($nearest) {
                // Ubah '2025-11-1' -> 'Das 1 Nov 2025'
                $labels[] = $this->formatDasLabel($period);
                $data[] = round($nearest->val, 2);
            }
        }
        return !empty($labels) ? ['labels' => $labels, 'data' => $data] : null;
    }

    // --- Helper untuk memformat label dasarian ---
    private function formatDasLabel($period_string)
    {
        try {
            // $period_string = "2025-11-1"
            $parts = explode('-', $period_string);
            $date = \Carbon\Carbon::createFromDate($parts[0], $parts[1], 1);
            $monthYear = $date->format('M Y'); // e.g., "Nov 2025"
            $das = $parts[2];
            return "Das $das $monthYear";
        } catch (\Exception $e) {
            return $period_string; // Fallback
        }
    }

    // --- FUNGSI UNTUK MEMANGGIL NARASI ---
    private function generateIntroNarrative($locationName)
    {
        return "Informasi berikut menggambarkan kondisi curah hujan di wilayah <strong>{$locationName}</strong>. Data yang ditampilkan terdiri dari curah hujan normal tahunan, analisis curah hujan 3 bulan terakhir, serta prediksi curah hujan untuk beberapa bulan ke depan. Visualisasi ini diharapkan dapat membantu dalam memahami pola hujan, kondisi terkini, serta prediksi cuaca untuk mendukung kegiatan masyarakat maupun perencanaan sektor terkait.";
    }

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

        $rainyMonths = [];
        $dryMonths = [];
        foreach ($data as $index => $val) {
            if ($val >= 150)
                $rainyMonths[] = $monthNames[$index];
            else
                $dryMonths[] = $monthNames[$index];
        }

        $narrative = "Grafik ini menunjukkan rata-rata pola curah hujan normal di <strong>{$locationName}</strong> sepanjang tahun. ";

        if (!empty($rainyMonths)) {
            $narrative .= "Tampak bahwa periode dengan curah hujan tinggi umumnya terjadi pada bulan " . implode(', ', $rainyMonths) . ", dengan puncak hujan sekitar <strong>{$peakMonth}</strong> yang mencapai " . round($maxRain) . " mm/bulan. ";
        }

        if (!empty($dryMonths)) {
            $narrative .= "Sementara itu, pada bulan " . implode(', ', $dryMonths) . ", curah hujan menurun, bahkan bisa berada di bawah batas musim kemarau (150 mm/bulan), dengan titik terendah sekitar " . round($minRain) . " mm pada bulan <strong>{$troughMonth}</strong>. ";
        }

        $narrative .= "Hal ini menggambarkan pola iklim musiman di <strong>{$locationName}</strong> yang dipengaruhi oleh peralihan musim hujan dan kemarau.";
        return $narrative;
    }

    private function generateAnalysisNarrative($analysisData, $locationName)
    {
        // Pemeriksaan data yang disesuaikan (tanpa data bayangan)
        if (empty($analysisData['data']) || count($analysisData['data']) < 2) {
            return "";
        }

        // 1. Langsung gunakan data yang ada karena tidak ada data bayangan
        $real_labels = $analysisData['labels'];
        $real_data = $analysisData['data'];
        $real_upper_bounds = $analysisData['upper_bounds'];
        $real_lower_bounds = $analysisData['lower_bounds'];
        $num_real_points = count($real_data);

        $above_normal_months = [];
        $below_normal_months = [];
        $normal_months = [];

        // 2. Klasifikasikan setiap bulan berdasarkan kondisinya
        foreach ($real_data as $index => $value) {
            $month_name = date('F', strtotime($real_labels[$index]));
            $month_info = "{$month_name} (" . round($value) . " mm)";

            if ($value > $real_upper_bounds[$index]) {
                $above_normal_months[] = $month_info;
            } elseif ($value < $real_lower_bounds[$index]) {
                $below_normal_months[] = $month_info;
            } else {
                $normal_months[] = $month_info;
            }
        }

        // 3. Bangun kalimat narasi secara dinamis
        $narrative = "Grafik analisis ini membandingkan curah hujan aktual di <strong>{$locationName}</strong> selama {$num_real_points} bulan terakhir terhadap rentang kondisi normalnya. Warna pada diagram menunjukkan statusnya: <strong>hijau (di atas normal)</strong>, <strong>kuning (normal)</strong>, dan <strong>coklat (di bawah normal)</strong>.<br><br>";

        $summary_parts = [];
        if (!empty($normal_months)) {
            $summary_parts[] = "kondisi <strong>normal</strong> teramati pada bulan " . implode(', ', $normal_months);
        }
        if (!empty($above_normal_months)) {
            $summary_parts[] = "kondisi <strong>di atas normal (lebih basah)</strong> terjadi pada bulan " . implode(', ', $above_normal_months);
        }
        if (!empty($below_normal_months)) {
            $summary_parts[] = "kondisi <strong>di bawah normal (lebih kering)</strong> terlihat pada bulan " . implode(', ', $below_normal_months);
        }

        if (!empty($summary_parts)) {
            $narrative .= " Dari data yang telah divisualisasikan, terlihat bahwa " . implode(', sedangkan ', $summary_parts) . ". ";
        }

        // 4. Berikan insight/kesimpulan penutup berdasarkan pola data
        $last_value = end($real_data);
        $last_upper_bound = end($real_upper_bounds);
        $last_lower_bound = end($real_lower_bounds);

        if ($last_value > $last_upper_bound) {
            $narrative .= "Pola ini mengindikasikan bahwa wilayah <strong>{$locationName}</strong> baru-baru ini memasuki periode yang cenderung <strong>lebih basah</strong> dari biasanya.";
        } elseif ($last_value < $last_lower_bound) {
            $narrative .= "Pola ini menunjukkan adanya tren <strong>kondisi yang lebih kering</strong> dari biasanya di wilayah <strong>{$locationName}</strong>.";
        } else {
            if (count($summary_parts) > 1) {
                $narrative .= "Secara keseluruhan, data menunjukkan adanya <strong>fluktuasi kondisi curah hujan</strong> yang signifikan dalam beberapa waktu terakhir.";
            }
        }

        return $narrative;
    }

    private function generatePredictionNarrative($predictionData, $locationName)
    {
        if (empty($predictionData['data']))
            return "";

        $data = $predictionData['data'];
        $labels = $predictionData['labels'];
        $count = count($data);

        $maxRain = max($data);
        $peakMonthIndex = array_search($maxRain, $data);
        $peakMonth = date('F Y', strtotime($labels[$peakMonthIndex]));
        $startMonth = date('F Y', strtotime($labels[0]));
        $endMonth = date('F Y', strtotime($labels[$count - 1]));

        return "Berdasarkan prediksi, curah hujan di <strong>{$locationName}</strong> untuk periode {$startMonth} hingga {$endMonth} diperkirakan akan berfluktuasi. Puncak curah hujan tertinggi diproyeksikan terjadi pada bulan <strong>{$peakMonth}</strong> dengan curah hujan sekitar " . round($maxRain) . " mm/bulan. Proyeksi ini menunjukkan bahwa wilayah <strong>{$locationName}</strong> kemungkinan akan memasuki musim hujan dengan intensitas bervariasi dalam beberapa bulan mendatang, sehingga perlu perhatian pada sektor pertanian, tata kelola air, dan potensi bencana hidrometeorologi.";
    }

    private function generateDasPredictionNarrative($dasPredictionData, $locationName)
    {
        if (empty($dasPredictionData['data'])) return "";

        $count = count($dasPredictionData['labels']);
        $startLabel = $dasPredictionData['labels'][0];
        $endLabel = $dasPredictionData['labels'][$count - 1];

        return "Untuk prediksi jangka pendek, grafik ini menunjukkan prediksi curah hujan dasarian (10 harian) di <strong>{$locationName}</strong> untuk 3 periode ke depan, dari <strong>{$startLabel}</strong> hingga <strong>{$endLabel}</strong>. Data ini memberikan gambaran lebih rinci mengenai potensi hujan dalam 30 hari ke depan.";
    }


    // --- FUNGSI UTAMA (DIMODIFIKASI) ---
    public function getClimateData(Request $request)
    {
        // Validasi input tetap 'kecamatan'
        $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $userInput = $request->input('kecamatan');
        $targetLat = null;
        $targetLon = null;
        $locationName = $userInput;

        // --- [PERBAIKAN 1: REGEX UNTUK KOORDINAT] ---

        if (preg_match('/^\(?\s*([-]?\d{1,3}(?:\.\d+)?)\s*,\s*([-]?\d{1,3}(?:\.\d+)?)\s*\)?$/', $userInput, $matches)) {
            $lat = $matches[1];
            $lon = $matches[2];

            $normalData = $this->getNormalData($lat, $lon, null, null);
        } else {
            $parts = array_map('trim', explode(',', $userInput));
            $kecamatan = $parts[0];
            $regency = $parts[1] ?? null; // Ambil kabupaten jika ada
            // Pencarian nama HANYA akan mencari KECAMATAN
            $normalData = $this->getNormalData(null, null, $kecamatan, $regency);
        }
        // --- [AKHIR PERBAIKAN 1] ---


        // --- PESAN ERROR DIPERBAIKI ---
        if (!$normalData) {
            // Berikan pesan error yang lebih spesifik
            return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan. Pastikan nama kecamatan atau format koordinat (lat, lon) benar.'], 404);
        }

        $targetLat = $normalData['coords']['lat'];
        $targetLon = $normalData['coords']['lon'];
        $locationName = $normalData['locationName'];

        // --- PEMANGGILAN DATA ANALISIS DAN PREDIKSI ---
        $normal_upper_bounds = array_map(fn($val) => round($val * 1.15, 2), $normalData['data']);
        $normal_lower_bounds = array_map(fn($val) => round($val * 0.85, 2), $normalData['data']);
        $normal_bounds_lookup = [];
        for ($i = 0; $i < 12; $i++) {
            // Gunakan nomor bulan (1-12) sebagai kunci/key
            $normal_bounds_lookup[$i + 1] = [
                'upper' => $normal_upper_bounds[$i],
                'lower' => $normal_lower_bounds[$i]
            ];
        }
        // --------------------------------------------------------------------

        // Sekarang $targetLat dan $targetLon sudah terisi dengan benar
        $analysisData = $this->getAnalysisData($targetLat, $targetLon, $normal_bounds_lookup);
        $dasPredictionData = $this->getDasPredictionData($targetLat, $targetLon);
        $predictionData = $this->getPredictionData($targetLat, $targetLon);

        // --- PEMANGGILAN FUNGSI NARASI ---
        $introNarrative = $this->generateIntroNarrative($locationName);
        $normalNarrative = $this->generateNormalNarrative($normalData, $locationName);
        $analysisNarrative = $analysisData ? $this->generateAnalysisNarrative($analysisData, $locationName) : null;
        $dasPredictionNarrative = $dasPredictionData ? $this->generateDasPredictionNarrative($dasPredictionData, $locationName) : null;
        $predictionNarrative = $predictionData ? $this->generatePredictionNarrative($predictionData, $locationName) : null;

        // --- PEMANGGILAN DATA UNTUK 24 BULAN ---
        $labels_24_months = array_merge(
            ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
            ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC']
        );
        $data_24_months = array_merge($normalData['data'], $normalData['data']);

        // --- TAMBAHAN: HITUNG BATAS ATAS DAN BATAS BAWAH ---
        $data_upper_bound = array_map(fn($val) => round($val * 1.15, 2), $data_24_months);
        $data_lower_bound = array_map(fn($val) => round($val * 0.85, 2), $data_24_months);

        return response()->json([
            'locationName' => $locationName,
            'intro_narrative' => $introNarrative,
            'normal' => [
                'labels' => $labels_24_months,
                'data' => $data_24_months,
                'data_upper_bound' => $data_upper_bound,
                'data_lower_bound' => $data_lower_bound,
                'narrative' => $normalNarrative,
            ],
            'analysis' => $analysisData ? array_merge($analysisData, ['narrative' => $analysisNarrative]) : null,
            'das_prediction' => $dasPredictionData ? array_merge($dasPredictionData, ['narrative' => $dasPredictionNarrative]) : null,
            'prediction' => $predictionData ? array_merge($predictionData, ['narrative' => $predictionNarrative]) : null,
        ]);
    }

    // di ChatController.php
    public function searchKecamatan(Request $request)
    {
        $term = $request->query('term');
        if (empty($term) || strlen($term) < 2) {
            return response()->json([]);
        }

        // 1. Ambil 'kecamatan' DAN 'regency'
        $data = ClimateNormal::select('kecamatan', 'regency')
            ->where('kecamatan', 'LIKE', $term . '%')
            ->distinct() // Ambil pasangan unik
            ->limit(10)
            ->get();

        // 2. Format menjadi OBJEK JSON (BUKAN STRING)
        $results = $data->map(function ($item) {
            $kecamatan = ucwords(strtolower($item->kecamatan));
            $regency = ucwords(strtolower($item->regency));

            return [
                // 'value' -> data yang akan masuk ke input box
                'value' => $kecamatan,
                // 'display' -> data yang akan tampil di dropdown
                'display' => $kecamatan . ', ' . $regency
            ];
        });

        return response()->json($results);
    }
}