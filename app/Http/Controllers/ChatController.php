<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClimateData;
use Illuminate\Support\Facades\DB;
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
        $userInputLower = strtolower($userInput); // Gunakan variabel ini untuk perbandingan

        // --- DETEKSI INPUT: KOORDINAT (LAT, LON) ---
        if (preg_match('/^[-]?\d{1,3}\.\d+,\s*[-]?\d{1,3}\.\d+$/', $userInput)) {
            list($lat, $lon) = array_map('trim', explode(',', $userInput));
            
            $nearestPoint = ClimateData::select(
                '*',
                DB::raw("SQRT(POW(lat - ($lat), 2) + POW(lon - ($lon), 2)) AS distance")
            )
            ->orderBy('distance', 'asc')
            ->first();

            if ($nearestPoint) {
                $dataValues = [];
                foreach ($months as $month) {
                    $dataValues[] = (float) $nearestPoint->$month;
                }
                $responsePayload = [
                    'name' => $nearestPoint->ID_KABKOTA_IKN,
                    'data' => $dataValues
                ];
                $locationDetails = [
                    'lat_input' => $lat,
                    'lon_input' => $lon,
                    'provinsi' => $nearestPoint->ID_PROV38,
                    'kabupaten' => $nearestPoint->ID_KABKOTA_IKN
                ];
            }
        } else {
            // --- DETEKSI INPUT: NAMA PROVINSI ---
            $avgProv = DB::table('id_grid_chprovkab_jawa')
                ->select(
                    'ID_PROV38', // Pilih kolom asli untuk pengelompokan
                    ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months)
                )
                ->where(DB::raw('LOWER(ID_PROV38)'), $userInputLower) // Cara yang lebih aman
                ->groupBy('ID_PROV38')
                ->first();

            if ($avgProv) {
                $dataValues = [];
                foreach ($months as $month) {
                    $dataValues[] = (float) $avgProv->$month;
                }
                $responsePayload = [
                    'name' => $avgProv->ID_PROV38, // Gunakan nama dari hasil query
                    'data' => $dataValues
                ];
            } else {
                // --- DETEKSI INPUT: NAMA KABUPATEN/KOTA ---
                $avgKab = DB::table('id_grid_chprovkab_jawa')
                    ->select(
                        'ID_KABKOTA_IKN', // Pilih kolom asli untuk pengelompokan
                        ...array_map(fn($m) => DB::raw("AVG(`$m`) as `$m`"), $months)
                    )
                    ->where(DB::raw('LOWER(ID_KABKOTA_IKN)'), $userInputLower) // Cara yang lebih aman
                    ->groupBy('ID_KABKOTA_IKN')
                    ->first();
                
                if ($avgKab) {
                    $dataValues = [];
                    foreach ($months as $month) {
                        $dataValues[] = (float) $avgKab->$month;
                    }
                    $responsePayload = [
                        'name' => $avgKab->ID_KABKOTA_IKN, // Gunakan nama dari hasil query
                        'data' => $dataValues
                    ];
                }
            }
        }

        // --- Finalisasi Response ---
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

        return response()->json(['error' => 'Data untuk "' . $userInput . '" tidak ditemukan.'], 404);
    }
}