{{-- File ini berisi SEMUA versi narasi Prediksi Bulanan --}}
@php
    $namaLokasi = "<strong>" . e($locationName) . "</strong>";
@endphp

@switch($templateId)

    @case(1)
        {{-- Versi 1 (Asli) --}}
        Grafik selanjutnya menunjukkan prediksi curah hujan bulanan di Kecamatan {!! $namaLokasi !!} untuk periode 6 bulan ke depan. Berdasarkan grafik tersebut, {!! $namaLokasi !!} pada bulan <strong>{{ $startMonth }}</strong> hingga <strong>{{ $endMonth }}</strong> diprediksi akan mengalami curah hujan berturut-turut {!! $listDetails !!}
        @break

    @case(2)
        {{-- Versi 2 (Dari Anda, disesuaikan) --}}
        Prediksi curah hujan bulanan di Kecamatan {!! $namaLokasi !!} untuk periode enam bulan ke depan (<strong>{{ $startMonth }}</strong> - <strong>{{ $endMonth }}</strong>) ditunjukkan dalam grafik berikut. Curah hujan diperkirakan berturut-turut {!! $listDetails !!}
        @break

    @case(3)
        {{-- Versi 3 (Dari Anda, disesuaikan) --}}
        Grafik berikut memperlihatkan prakiraan curah hujan bulanan di Kecamatan {!! $namaLokasi !!} selama enam bulan mendatang. Dari <strong>{{ $startMonth }}</strong> hingga <strong>{{ $endMonth }}</strong>, curah hujan diperkirakan berturut-turut {!! $listDetails !!}
        @break

    @case(4)
        {{-- Versi 4 (Dari Anda, disesuaikan) --}}
        Pada grafik prediksi berikut, terlihat prediksi curah hujan bulanan di Kecamatan {!! $namaLokasi !!} untuk periode enam bulan ke depan (<strong>{{ $startMonth }}</strong>–<strong>{{ $endMonth }}</strong>). Nilai curah hujan diprediksi berturut-turut {!! $listDetails !!}
        @break

    @default
        {{-- Versi 5 (Default/Case 5 - Dari Anda, disesuaikan) --}}
        Grafik ini menampilkan prediksi curah hujan bulanan untuk wilayah {!! $namaLokasi !!}. Dari <strong>{{ $startMonth }}</strong> sampai <strong>{{ $endMonth }}</strong>, curah hujan diperkirakan masing-masing {!! $listDetails !!}
@endswitch