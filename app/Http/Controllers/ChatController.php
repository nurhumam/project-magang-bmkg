<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateNormal;
use App\Models\ClimateAnalysis;
use App\Models\ClimatePrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

    private function getNormalData($lat, $lon, $userInput)
    {
        $months = ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'];
        $avg_months_sql = implode(', ', array_map(fn($m) => "AVG(`$m`) as `$m`", $months));

        // Case 1: Pencarian berdasarkan koordinat (tidak ada perubahan)
        if ($lat && $lon) {
            $data = ClimateNormal::select('*', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->orderBy('distance', 'asc')->first();
        } else {
            // Case 2: Pencarian berdasarkan nama
            $isProvince = ClimateNormal::whereRaw('LOWER(province) = ?', [strtolower($userInput)])->exists();

            if ($isProvince) {
                // --- LOGIKA PROVINSI (DIPERBAIKI DENGAN selectRaw) ---
                $data = ClimateNormal::selectRaw(
                    "? as province, NULL as regency, AVG(latitude) as latitude, AVG(longitude) as longitude, $avg_months_sql",
                    [$userInput] // Mengikat userInput ke '?' pertama
                )
                    ->whereRaw('LOWER(province) = ?', [strtolower($userInput)])
                    ->first();
            } else {
                // --- LOGIKA KABUPATEN/KOTA (DIPERBAIKI DENGAN selectRaw) ---
                $data = ClimateNormal::selectRaw(
                    "MAX(province) as province, ? as regency, AVG(latitude) as latitude, AVG(longitude) as longitude, $avg_months_sql",
                    [$userInput] // Mengikat userInput ke '?' pertama
                )
                    ->where('regency', 'LIKE', "%$userInput%")
                    ->first();
            }
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
            'locationName' => ucwords(strtolower($data->regency ?: $data->province)),
            'data' => $dataValues,
            'coords' => ['lat' => $data->latitude, 'lon' => $data->longitude]
        ];
    }

    private function getAnalysisData($lat, $lon)
    {
        $periods = ClimateAnalysis::select('data_period')->distinct()
            ->orderBy('data_period', 'desc')->limit(3)->get()->pluck('data_period')->reverse()->values();

        if ($periods->count() < 2)
            return null;

        $labels = [];
        $data = [];
        foreach ($periods as $period) {
            $nearest = ClimateAnalysis::select('ch', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('data_period', $period)->orderBy('distance', 'asc')->first();
            if ($nearest) {
                $labels[] = $period;
                $data[] = round($nearest->ch, 2);
            }
        }
        return count($labels) >= 2 ? ['labels' => $labels, 'data' => $data] : null;
    }

    private function getPredictionData($lat, $lon)
    {
        $periods = ClimatePrediction::select('prediction_period')->distinct()
            ->orderBy('prediction_period', 'asc')->limit(6)->get()->pluck('prediction_period'); // Ambil 7 bulan

        if ($periods->isEmpty())
            return null;

        $labels = [];
        $data = [];
        foreach ($periods as $period) {
            $nearest = ClimatePrediction::select('val', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('prediction_period', $period)->orderBy('distance', 'asc')->first();
            if ($nearest) {
                $labels[] = $period;
                $data[] = round($nearest->val, 2);
            }
        }
        return !empty($labels) ? ['labels' => $labels, 'data' => $data] : null;
    }

    // --- FUNGSI BARU UNTUK MEMBUAT NARASI ---

    private function generateIntroNarrative($locationName)
    {
        return "Informasi berikut menggambarkan kondisi curah hujan di wilayah <strong>{$locationName}</strong>. Data yang ditampilkan terdiri dari curah hujan normal tahunan, analisis curah hujan 3 bulan terakhir, serta prediksi curah hujan untuk beberapa bulan ke depan. Visualisasi ini diharapkan dapat membantu dalam memahami pola hujan, kondisi terkini, serta proyeksi cuaca untuk mendukung kegiatan masyarakat maupun perencanaan sektor terkait.";
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
        if (empty($analysisData['data']) || count($analysisData['data']) < 2)
            return "";

        $data = $analysisData['data'];
        $labels = $analysisData['labels'];
        $count = count($data);

        $firstVal = round($data[0]);
        $lastVal = round($data[$count - 1]);
        $firstMonth = date('F Y', strtotime($labels[0]));
        $lastMonth = date('F Y', strtotime($labels[$count - 1]));

        $trend = "";
        if ($lastVal > $firstVal) {
            $trend = "menunjukkan tren peningkatan";
        } elseif ($lastVal < $firstVal) {
            $trend = "menunjukkan tren penurunan";
        } else {
            $trend = "cenderung stabil";
        }

        return "Grafik analisis menunjukkan bahwa curah hujan di <strong>{$locationName}</strong> dari bulan {$firstMonth} ({$firstVal} mm) hingga {$lastMonth} ({$lastVal} mm) {$trend}. Perubahan ini menandakan dinamika cuaca jangka pendek di wilayah tersebut.";
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


    // --- FUNGSI UTAMA (DIMODIFIKASI) ---
    public function getClimateData(Request $request)
    {
        $validator = Validator::make($request->all(), ['kecamatan' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $userInput = $request->input('kecamatan');
        $targetLat = null;
        $targetLon = null;
        $locationName = $userInput;

        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            list($lat, $lon) = array_map('trim', explode(',', $userInput));
            $normalData = $this->getNormalData($lat, $lon, null);
        } else {
            $normalData = $this->getNormalData(null, null, $userInput);
        }

        if (!$normalData) {
            return response()->json(['error' => 'Data Normal untuk "' . $userInput . '" tidak ditemukan.'], 404);
        }

        $targetLat = $normalData['coords']['lat'];
        $targetLon = $normalData['coords']['lon'];
        $locationName = $normalData['locationName'];

        $analysisData = $this->getAnalysisData($targetLat, $targetLon);
        $predictionData = $this->getPredictionData($targetLat, $targetLon);

        // --- PEMANGGILAN FUNGSI NARASI ---
        $introNarrative = $this->generateIntroNarrative($locationName);
        $normalNarrative = $this->generateNormalNarrative($normalData, $locationName);
        $analysisNarrative = $analysisData ? $this->generateAnalysisNarrative($analysisData, $locationName) : null;
        $predictionNarrative = $predictionData ? $this->generatePredictionNarrative($predictionData, $locationName) : null;

        return response()->json([
            'locationName' => $locationName,
            'intro_narrative' => $introNarrative, // Tambahkan ini
            'normal' => [
                'labels' => ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'],
                'data' => $normalData['data'],
                'narrative' => $normalNarrative, // Tambahkan ini
            ],
            'analysis' => $analysisData ? array_merge($analysisData, ['narrative' => $analysisNarrative]) : null, // Tambahkan ini
            'prediction' => $predictionData ? array_merge($predictionData, ['narrative' => $predictionNarrative]) : null, // Tambahkan ini
        ]);
    }
}