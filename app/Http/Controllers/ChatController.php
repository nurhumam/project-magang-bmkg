<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateData;
use App\Models\GeoCache;
use App\Models\HistoricalClimateData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Menampilkan halaman utama chat.
     */
    public function index()
    {
        return view('chat');
    }

    /**
     * Fungsi helper untuk mengambil 2 data historis terakhir sebagai perbandingan.
     *
     * @param float $lat
     * @param float $lon
     * @return array|null
     */
    private function getHistoricalData($lat, $lon)
    {
        // 1. Dapatkan 2 periode data terbaru dari tabel, diurutkan dari yang terlama
        $periods = HistoricalClimateData::select('data_period')
            ->distinct()
            ->orderBy('data_period', 'desc')
            ->limit(2)
            ->get()
            ->pluck('data_period')
            ->reverse() // Balik urutan agar periode terlama muncul pertama
            ->values();

        if ($periods->count() < 2) {
            return null; // Hanya proses jika ada minimal 2 periode data
        }

        $comparisonLabels = [];
        $comparisonData = [];

        foreach ($periods as $period) {
            // 2. Untuk setiap periode, cari titik terdekat dan ambil nilai CH-nya
            $nearestPoint = HistoricalClimateData::select('ch', DB::raw("SQRT(POW(latitude - ($lat), 2) + POW(longitude - ($lon), 2)) AS distance"))
                ->where('data_period', $period)
                ->orderBy('distance', 'asc')
                ->first();

            if ($nearestPoint) {
                $comparisonLabels[] = $period; // Contoh: "2025-06"
                $comparisonData[] = round($nearestPoint->ch, 2); // Contoh: 112.54
            }
        }
        
        if (count($comparisonLabels) < 2) {
            return null;
        }

        // 3. Kembalikan data dalam format baru yang sederhana
        return [
            'labels' => $comparisonLabels,
            'data' => $comparisonData,
        ];
    }

    /**
     * Endpoint API utama untuk mendapatkan data iklim.
     */
    public function getClimateData(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kecamatan' => 'required|string',
            'tahun' => 'required|integer|min:2000|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 400);
        }

        $userInput = $request->input('kecamatan');
        $userInputLower = strtolower($userInput);
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        // Inisialisasi variabel
        $targetLat = null;
        $targetLon = null;
        $locationName = $userInput;
        $normalPayload = null;
        $historicalComparison = null;
        $locationDetails = null;

        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            // --- BLOK UNTUK INPUT KOORDINAT ---
            list($lat, $lon) = array_map('trim', explode(',', $userInput));
            $targetLat = $lat;
            $targetLon = $lon;

            // 1. Dapatkan data Iklim Normal terdekat
            $nearestNormalPoint = ClimateData::select('*', DB::raw("SQRT(POW(lat - ($lat), 2) + POW(lon - ($lon), 2)) AS distance"))
                ->orderBy('distance', 'asc')->first();

            if ($nearestNormalPoint) {
                $dataValues = [];
                foreach ($months as $month) {
                    $dataValues[] = (float) $nearestNormalPoint->$month;
                }
                $locationName = $nearestNormalPoint->ID_KABKOTA_IKN;
                $normalPayload = ['name' => $locationName, 'data' => $dataValues];
            }

        } else {
            // --- BLOK UNTUK INPUT NAMA LOKASI ---
            $avgData = DB::table('id_grid_chprovkab_jawa')
                ->select('ID_KABKOTA_IKN as name', DB::raw('AVG(lat) as lat, AVG(lon) as lon'), ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months))
                ->where(DB::raw('LOWER(ID_KABKOTA_IKN)'), $userInputLower)
                ->groupBy('ID_KABKOTA_IKN')->first();

            if (!$avgData) {
                $avgData = DB::table('id_grid_chprovkab_jawa')
                    ->select('ID_PROV38 as name', DB::raw('AVG(lat) as lat, AVG(lon) as lon'), ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months))
                    ->where(DB::raw('LOWER(ID_PROV38)'), $userInputLower)
                    ->groupBy('ID_PROV38')->first();
            }

            if ($avgData) {
                $dataValues = [];
                foreach ($months as $month) {
                    $dataValues[] = (float) $avgData->$month;
                }
                $locationName = $avgData->name;
                $normalPayload = ['name' => $locationName, 'data' => $dataValues];
                $targetLat = $avgData->lat;
                $targetLon = $avgData->lon;
            }
        }

        // --- SETELAH KOORDINAT TARGET DITENTUKAN, CARI DATA HISTORIS ---
        if ($targetLat && $targetLon) {
            $historicalComparison = $this->getHistoricalData($targetLat, $targetLon);
        }

        // --- KIRIM RESPON FINAL ---
        if ($normalPayload || $historicalComparison) {
            return response()->json([
                'kecamatan' => $locationName,
                'comparison_data' => $historicalComparison,
                'normal' => $normalPayload ? [
                    'kecamatan' => $locationName,
                    'labels' => $months,
                    'data' => $normalPayload['data'],
                    'chart_labels' => array_merge($months, $months),
                    'chart_data' => array_merge($normalPayload['data'], $normalPayload['data'])
                ] : null,
                'location_details' => $locationDetails
            ]);
        }

        return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan.'], 404);
    }
}