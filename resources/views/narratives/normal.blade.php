@php
    $namaLokasi = "<strong>" . e($locationName) . "</strong>";
    $valMaxRain = "<strong>" . e($maxRain) . " mm/bulan</strong>";
    $valMinRain = "<strong>" . e($minRain) . " mm/bulan</strong>";
    $valPeakMonth = "<strong>" . e($peakMonth) . "</strong>";
    $valTroughMonth = "<strong>" . e($troughMonth) . "</strong>";

    $formatListAnd = function (array $items) {
        if (empty($items)) return null;
        $count = count($items);
        if ($count === 1) return $items[0];
        if ($count === 2) return implode(' dan ', $items);
        $lastItem = array_pop($items);
        return implode(', ', $items) . ', dan ' . $lastItem;
    };

    // Helper untuk menyederhanakan daftar bulan
    $isAllKategoriTinggi = count($kategoriTinggiMonths) === 12;
    $isAllKategoriRendah = count($kategoriRendahMonths) === 12;
    $isAllMusimKemarau = count($musimKemarauMonths) === 12;

    // Hitung list akhir menggunakan helper baru
    $listTinggi = $isAllKategoriTinggi ? 'Januari hingga Desember' : $formatListAnd($kategoriTinggiMonths);
    $listRendah = $isAllKategoriRendah ? 'Januari hingga Desember' : $formatListAnd($kategoriRendahMonths);
    $listKemarau = $isAllMusimKemarau ? 'Januari hingga Desember' : $formatListAnd($musimKemarauMonths);
@endphp

@switch($templateId)

    @case(1)
        {{-- Versi 1 --}}
        Analisis pola curah hujan bulanan di Kecamatan {!! $namaLokasi !!} menunjukkan variasi yang jelas.
        @if($listTinggi)
            Curah hujan kategori <strong>tinggi (>300 mm/bulan)</strong> umumnya terjadi pada bulan {!! $listTinggi !!}.
        @else
            Sepanjang tahun, curah hujan di wilayah ini umumnya tidak pernah mencapai kategori tinggi (>300 mm/bulan).
        @endif

        @if($listRendah)
            Sedangkan curah hujan kategori <strong>rendah (<=100 mm/bulan)</strong> umumnya terjadi pada bulan {!! $listRendah !!}.
        @else
            Di sisi lain, curah hujan di wilayah ini terpantau selalu berada di atas 100 mm/bulan sepanjang tahun.
        @endif

        Curah hujan tertinggi terjadi pada bulan {!! $valPeakMonth !!} yang mencapai {!! $valMaxRain !!}, sedangkan curah hujan terendah terjadi pada bulan {!! $valTroughMonth !!} yaitu berkisar {!! $valMinRain !!}.
        @if($listKemarau)
            Musim kemarau (curah hujan < 150 mm/bulan) umumnya terjadi pada bulan {!! $listKemarau !!}.
        @else
            Wilayah ini umumnya tidak mengalami musim kemarau (curah hujan selalu di atas 150 mm/bulan).
        @endif
        @break

    @case(2)
        {{-- Versi 2 --}}
        Pada grafik ini ditampilkan variasi curah hujan bulanan di wilayah {!! $namaLokasi !!}. Bulan {!! $valPeakMonth !!} menunjukkan curah hujan tertinggi, mencapai {!! $valMaxRain !!}, sedangkan bulan {!! $valTroughMonth !!} mencatat curah hujan terendah, sekitar {!! $valMinRain !!}.
        
        @if($listTinggi)
            Umumnya, kategori hujan tinggi (>300 mm) terjadi pada bulan {!! $listTinggi !!}.
        @endif
        @if($listRendah)
            Sementara kategori rendah (< 100 mm) terjadi pada bulan {!! $listRendah !!}.
        @endif
        @if($listKemarau)
            Musim kemarau diperkirakan berlangsung pada bulan {!! $listKemarau !!}.
        @endif
        @break

    @case(3)
        {{-- Versi 3 --}}
        Grafik berikut memperlihatkan kondisi rata-rata curah hujan bulanan di Kecamatan {!! $namaLokasi !!}.
        
        @if($listTinggi)
            Curah hujan tinggi biasanya terjadi pada bulan {!! $listTinggi !!}.
        @endif
        @if($listRendah)
            Curah hujan rendah tercatat pada bulan {!! $listRendah !!}.
        @endif
        
        Nilai tertinggi mencapai {!! $valMaxRain !!} di bulan {!! $valPeakMonth !!}, sementara nilai terendah berada di kisaran {!! $valMinRain !!} pada bulan {!! $valTroughMonth !!}.
        @if($listKemarau)
            Musim kemarau umumnya terjadi pada bulan {!! $listKemarau !!}.
        @endif
        @break

    @case(4)
        {{-- Versi 4 --}}
        Grafik ini menunjukkan pola curah hujan di Kecamatan {!! $namaLokasi !!}. 
        
        @if($listTinggi && $listRendah)
            Terlihat bahwa curah hujan tinggi (>300 mm/bulan) terjadi pada bulan {!! $listTinggi !!}, sedangkan curah hujan rendah (< 100 mm/bulan) terjadi pada bulan {!! $listRendah !!}.
        @elseif($listTinggi)
            Terlihat bahwa curah hujan tinggi (>300 mm/bulan) terjadi pada bulan {!! $listTinggi !!}.
        @elseif($listRendah)
            Terlihat bahwa curah hujan rendah (< 100 mm/bulan) terjadi pada bulan {!! $listRendah !!}.
        @endif
        
        Puncak curah hujan tercatat di bulan {!! $valPeakMonth !!} dengan intensitas sebesar {!! $valMaxRain !!}, dan curah hujan terendah pada bulan {!! $valTroughMonth !!} sebesar {!! $valMinRain !!}.
        @if($listKemarau)
            Umumnya, periode kemarau berlangsung pada bulan {!! $listKemarau !!}.
        @endif
        @break

    @default
        {{-- Versi 5 --}}
        Grafik ini menunjukkan rata-rata distribusi hujan bulanan di wilayah {!! $namaLokasi !!}. Bulan {!! $valPeakMonth !!} menjadi puncak dengan curah hujan sebesar {!! $valMaxRain !!}, sedangkan bulan {!! $valTroughMonth !!} menunjukkan nilai curah hujan terendah yaitu sekitar {!! $valMinRain !!}.
        
        @if($listTinggi)
            Kategori curah hujan tinggi (>300 mm/bulan) terjadi pada bulan {!! $listTinggi !!}.
        @endif
        @if($listRendah)
            Curah hujan rendah (< 100 mm/bulan) terjadi pada bulan {!! $listRendah !!}.
        @endif
        
        @if($listKemarau)
            Musim kemarau berlangsung pada bulan {!! $listKemarau !!}.
        @endif
@endswitch