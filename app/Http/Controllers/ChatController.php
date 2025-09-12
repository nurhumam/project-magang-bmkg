<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateData;
use App\Models\GeoCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    public function index()
    {
        return view('chat');
    }

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
        $requestedYear = $request->input('tahun');
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        $responsePayload = null;
        $locationDetails = null;
        $userInputLower = strtolower($userInput);

        // --- DETEKSI INPUT: KOORDINAT (LAT, LON) ---
        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            list($lat, $lon) = array_map('trim', explode(',', $userInput));

            // LANGKAH 1: Langsung cari data iklim terdekat DULU. Ini wajib berhasil.
            $nearestPoint = ClimateData::select('*', DB::raw("SQRT(POW(lat - ($lat), 2) + POW(lon - ($lon), 2)) AS distance"))
                ->orderBy('distance', 'asc')->first();

            // Jika data iklim lokal TIDAK ADA, maka hentikan proses.
            if (!$nearestPoint) {
                return response()->json(['error' => 'Tidak ada data iklim yang ditemukan di database lokal.'], 404);
            }

            // LANGKAH 2: Coba dapatkan detail alamat (dari cache atau API). Ini bersifat opsional.
            $roundedLat = round($lat, 4);
            $roundedLon = round($lon, 4);
            $addressData = null;

            $cachedLocation = GeoCache::where('latitude', $roundedLat)->where('longitude', $roundedLon)->first();

            if ($cachedLocation) {
                $addressData = $cachedLocation->toArray();
            } else {
                try {
                    // --- PERUBAHAN UTAMA: MENGGUNAKAN API DARI BIG ---
                    $geoResponse = Http::withHeaders([
                        // Ganti 'YOUR_API_KEY' dengan kunci API Anda jika diperlukan.
                        // Jika tidak perlu, Anda bisa menghapus atau mengosongkan header ini.
                        'Authorization' => 'Bearer YOUR_API_KEY'
                    ])->timeout(15)->get("https://api.ina-sdi.or.id/geospasial/reverse", [
                                'lat' => $lat,
                                'lon' => $lon,
                            ]);

                    if ($geoResponse->successful() && isset($geoResponse->json()['data'])) {
                        $result = $geoResponse->json()['data'];

                        // Menyesuaikan dengan struktur respons dari API BIG
                        $desa = $result['desa'] ?? $result['kelurahan'] ?? $nearestPoint->ID_KABKOTA_IKN;

                        $newCache = GeoCache::create([
                            'latitude' => $roundedLat,
                            'longitude' => $roundedLon,
                            'desa' => $desa,
                            'kecamatan' => $result['kecamatan'] ?? null,
                            'kabupaten' => $result['kabupaten'] ?? $result['kota'] ?? null,
                            'provinsi' => $result['provinsi'] ?? null,
                            'display_name' => $result['display_name'] ?? "$desa, {$result['kecamatan']}",
                        ]);
                        $addressData = $newCache->toArray();
                    }
                } catch (\Exception $e) {
                    Log::error('API Geocoding BIG Gagal: ' . $e->getMessage());
                }
            }

            // LANGKAH 3: Tentukan nama lokasi dan detailnya.
            $locationName = $addressData['desa'] ?? $nearestPoint->ID_KABKOTA_IKN; // Fallback ke nama kabupaten
            if ($addressData) {
                $locationDetails = [
                    'lat_input' => $lat,
                    'lon_input' => $lon,
                    'desa' => $addressData['desa'],
                    'kecamatan' => $addressData['kecamatan'],
                    'kabupaten' => $addressData['kabupaten'],
                    'provinsi' => $addressData['provinsi']
                ];
            } else {
                $locationDetails = [
                    'lat_input' => $lat,
                    'lon_input' => $lon,
                    'provinsi' => $nearestPoint->ID_PROV38,
                    'kabupaten' => $nearestPoint->ID_KABKOTA_IKN
                ];
            }

            // Siapkan payload dengan data iklim dan nama lokasi yang sudah ditentukan.
            $dataValues = [];
            foreach ($months as $month) {
                $dataValues[] = (float) $nearestPoint->$month;
            }
            $responsePayload = ['name' => $locationName, 'data' => $dataValues];

        } else {
            // --- Logika untuk provinsi dan kabupaten/kota ---
            $avgProv = DB::table('id_grid_chprovkab_jawa')
                ->select('ID_PROV38', ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months))
                ->where(DB::raw('LOWER(ID_PROV38)'), $userInputLower)
                ->groupBy('ID_PROV38')->first();

            if ($avgProv) {
                $dataValues = [];
                foreach ($months as $month) {
                    $dataValues[] = (float) $avgProv->$month;
                }
                $responsePayload = ['name' => $avgProv->ID_PROV38, 'data' => $dataValues];
            } else {
                $avgKab = DB::table('id_grid_chprovkab_jawa')
                    ->select('ID_KABKOTA_IKN', ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months))
                    ->where(DB::raw('LOWER(ID_KABKOTA_IKN)'), $userInputLower)
                    ->groupBy('ID_KABKOTA_IKN')->first();

                if ($avgKab) {
                    $dataValues = [];
                    foreach ($months as $month) {
                        $dataValues[] = (float) $avgKab->$month;
                    }
                    $responsePayload = ['name' => $avgKab->ID_KABKOTA_IKN, 'data' => $dataValues];
                }
            }
        }

        if ($responsePayload) {
            return response()->json([
                'kecamatan' => $responsePayload['name'],
                'requested_year' => $requestedYear,
                'time_series' => null,
                'normal' => [
                    'labels' => $months,
                    'data' => $responsePayload['data'],
                    'chart_labels' => array_merge($months, $months),
                    'chart_data' => array_merge($responsePayload['data'], $responsePayload['data'])
                ],
                'location_details' => $locationDetails
            ]);
        }

        return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan, atau lokasi geografis tidak dapat ditentukan.'], 404);
    }
}