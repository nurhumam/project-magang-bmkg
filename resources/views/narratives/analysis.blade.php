@php
    $namaLokasi = "<strong>" . e($locationName) . "</strong>";
@endphp

@switch($templateId)

    @case(1)
        {{-- Versi 1 --}}
        Grafik analisis ini membandingkan curah hujan aktual di wilayah {!! $namaLokasi !!} selama {{ $num_real_points }} bulan terakhir terhadap rentang kondisi normalnya. Warna pada diagram menunjukkan statusnya: <strong>hijau (di atas normal)</strong>, <strong>kuning (normal)</strong>, dan <strong>coklat (di bawah normal)</strong>.<br><br>
        {!! $summary !!}
        {!! $conclusion !!}
        @break

    @case(2)
        {{-- Versi 2 --}}
        Analisis pada grafik ini menunjukkan bagaimana curah hujan {{ $num_real_points }} bulan terakhir pada wilayah {!! $namaLokasi !!} dibandingkan dengan kondisi normalnya. Warna hijau menandakan hujan di atas normal, kuning untuk normal, dan coklat untuk di bawah normal.<br><br>
        {!! $summary !!}
        {!! $conclusion !!}
        @break

    @case(3)
        {{-- Versi 3 --}}
        Grafik ini menampilkan hasil analisis perbandingan curah hujan aktual dengan kondisi normal di wilayah {!! $namaLokasi !!} selama {{ $num_real_points }} bulan terakhir. Setiap warna menunjukkan kategori tertentu: hijau (atas normal), kuning (normal), dan coklat (bawah normal).<br><br>
        {!! $summary !!}
        {!! $conclusion !!}
        @break

    @case(4)
        {{-- Versi 4 --}}
        Dalam grafik ini ditunjukkan analisis kondisi curah hujan {{ $num_real_points }} bulan terakhir pada wilayah {!! $namaLokasi !!} terhadap nilai normalnya. Warna hijau mewakili hujan atas normal, kuning menandakan normal, dan coklat menunjukkan bawah normal.<br><br>
        {!! $summary !!}
        {!! $conclusion !!}
        @break

    @default
        {{-- Versi 5 --}}
        Grafik analisis berikut membandingkan curah hujan aktual {{ $num_real_points }} bulan terakhir dengan kondisi rata-rata normalnya di {!! $namaLokasi !!}. Warna hijau, kuning, dan coklat masing-masing menunjukkan kondisi atas normal, normal, dan bawah normal.<br><br>
        {!! $summary !!}
        {!! $conclusion !!}
@endswitch