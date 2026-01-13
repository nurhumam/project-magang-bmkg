@php
    $namaLokasi = "<strong>" . e($locationName) . "</strong>";
@endphp

@switch($templateId)

    @case(1)
        {{-- Versi 1 --}}
        Grafik sebelah kiri menunjukkan prediksi curah hujan dasarian di wilayah {!! $namaLokasi !!}. Telihat bahwa wilayah tersebut diprediksi akan mengalami curah hujan {!! $listDetailsPred !!}
        @if($listDetailsProb)
            <br><br>Kemudian, pada grafik di sebelah kanan menunjukkan prediksi peluang terjadinya curah hujan <span class="prob-threshold">{!! $defaultProbThreshold !!}</span>. Pada tiga dasarian mendatang, peluang curah hujan tersebut diperkirakan mencapai <span class="prob-details">{!! $listDetailsProb !!}</span>.
        @endif
        @break

    @case(2)
        {{-- Versi 2 --}}
        Grafik bagian kiri menunjukkan prediksi curah hujan dasarian di wilayah {!! $namaLokasi !!} ditampilkan pada grafik berikut. Diperkirakan curah hujan {!! $listDetailsPred !!}
        @if($listDetailsProb)
            <br><br>Pada grafik kanan terlihat prediksi kemungkinan curah hujan <span class="prob-threshold">{!! $defaultProbThreshold !!}</span>. Dalam tiga dasarian ke depan, peluang terjadinya curah hujan tersebut adalah <span class="prob-details">{!! $listDetailsProb !!}</span>.
        @endif
        @break

    @case(3)
        {{-- Versi 3 --}}
        Grafik bagian kiri ini menggambarkan prediksi curah hujan untuk setiap dasarian di wilayah {!! $namaLokasi !!}. Diprediksi curah hujan {!! $listDetailsPred !!}
        @if($listDetailsProb)
            <br><br>Sedangkan grafik bagian kanan menampilkan hasil prediksi peluang curah hujan <span class="prob-threshold">{!! $defaultProbThreshold !!}</span>. Selama tiga dasarian mendatang, peluang curah hujan tersebut masing-masing sebesar <span class="prob-details">{!! $listDetailsProb !!}</span>.
        @endif
        @break

    @case(4)
        {{-- Versi 4 --}}
        Berdasarkan grafik kiri prediksi, wilayah {!! $namaLokasi !!} diperkirakan akan mengalami curah hujan {!! $listDetailsPred !!}
        @if($listDetailsProb)
            <br><br>Prediksi pada grafik kanan menggambarkan peluang curah hujan <span class="prob-threshold">{!! $defaultProbThreshold !!}</span>. Untuk tiga dasarian berikutnya, peluang curah hujan tersebut diperkirakan mencapai <span class="prob-details">{!! $listDetailsProb !!}</span>.
        @endif
        @break

    @default
        {{-- Versi 5 --}}
        Grafik di sisi kiri memperlihatkan prediksi curah hujan dasarian di wilayah {!! $namaLokasi !!}. Curah hujan diprediksi {!! $listDetailsPred !!}
        @if($listDetailsProb)
            <br><br>Grafik di sisi kanan memperlihatkan prediksi peluang curah hujan <span class="prob-threshold">{!! $defaultProbThreshold !!}</span>. Dalam tiga dasarian mendatang, peluang terjadinya curah hujan tersebut masing-masing adalah <span class="prob-details">{!! $listDetailsProb !!}</span>.
        @endif
@endswitch