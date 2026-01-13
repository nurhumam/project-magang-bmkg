@php
    $namaLokasi = "<strong>" . e($locationName) . "</strong>";
@endphp

@switch($templateId)

    @case(1)
        {{-- Versi 1 --}}
        Informasi berikut menggambarkan kondisi curah hujan di wilayah {!! $namaLokasi !!}. Data yang ditampilkan terdiri dari curah hujan normal, analisis curah hujan 3 bulan terakhir, serta prediksi curah hujan untuk beberapa bulan ke depan. Visualisasi ini diharapkan dapat membantu dalam memahami pola hujan yang umumnya terjadi, kondisi curah hujan terkini, serta prediksi curah hujan beberapa bulan ke depan untuk mendukung kegiatan masyarakat maupun perencanaan sektor terkait.
        @break

    @case(2)
        {{-- Versi 2 --}}
        Berikut adalah informasi mengenai kondisi curah hujan di wilayah {!! $namaLokasi !!}. Data mencakup nilai curah hujan normal, analisis tiga bulan terakhir, serta prediksi curah hujan untuk periode mendatang. Melalui visualisasi ini, diharapkan pengguna dapat memahami dinamika hujan yang berlangsung serta menggunakan informasi tersebut dalam mendukung perencanaan di berbagai sektor.
        @break

    @case(3)
        {{-- Versi 3 --}}
        Narasi ini memberikan gambaran umum tentang kondisi curah hujan di wilayah {!! $namaLokasi !!}. Data yang disajikan terdiri dari curah hujan normal, hasil analisis tiga bulan terakhir, dan prediksi untuk bulan-bulan berikutnya. Informasi ini dapat dimanfaatkan untuk memahami pola hujan serta menunjang kegiatan masyarakat dan perencanaan pembangunan wilayah.
        @break

    @case(4)
        {{-- Versi 4 --}}
        Informasi berikut menggambarkan tren curah hujan di wilayah {!! $namaLokasi !!}. Disajikan data curah hujan normal, analisis tiga bulan terakhir, serta prediksi curah hujan beberapa bulan mendatang. Visualisasi ini diharapkan mampu memberikan wawasan tentang kondisi dan pola hujan yang terjadi di wilayah tersebut.
        @break

    @default
        {{-- Versi 5 --}}
        Laporan ini berisi informasi mengenai kondisi curah hujan di wilayah {!! $namaLokasi !!}. Data mencakup curah hujan normal, analisis kondisi hujan selama tiga bulan terakhir, serta prediksi curah hujan untuk bulan-bulan berikutnya. Informasi ini diharapkan dapat membantu masyarakat dan pihak terkait dalam memahami tren hujan dan mendukung perencanaan kegiatan di lapangan.
@endswitch