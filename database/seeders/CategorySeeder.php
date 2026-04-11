<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['category_name' => 'Tidak Berkategori', 'category_description' => 'Arsip yang belum ditetapkan ke dalam kategori tertentu atau masih dalam proses klasifikasi.'],
            ['category_name' => 'Peraturan Direksi', 'category_description' => 'Dokumen resmi yang berisi ketentuan atau peraturan yang ditetapkan oleh Direksi dan bersifat mengikat bagi seluruh unit kerja.'],
            ['category_name' => 'Curriculum Vitae', 'category_description' => 'Contoh atau format surat riwayat hidup (CV) yang digunakan sebagai dokumen pendukung dalam proses administrasi kepegawaian, seperti kenaikan jabatan.'],
            ['category_name' => 'NOTULEN/RISALAH RADIR/RUPS', 'category_description' => 'Dokumen hasil pencatatan resmi jalannya rapat Direksi (Radir) maupun Rapat Umum Pemegang Saham (RUPS), baik berupa notulen maupun risalah.'],
            ['category_name' => 'KREDIT', 'category_description' => 'Dokumen yang memuat informasi dan data terkait fasilitas kredit nasabah, baik yang masih aktif, sudah lunas, maupun sedang dalam proses penyelesaian.'],
            ['category_name' => 'KEPUTUSAN DIREKSI', 'category_description' => 'Dokumen yang berisi keputusan atau ketetapan resmi yang dikeluarkan oleh Direksi sebagai bagian dari pengambilan keputusan strategis.'],
            ['category_name' => 'AKTA', 'category_description' => 'Naskah-naskah resmi yang berkaitan dengan akta perusahaan, termasuk akta pendirian, perubahan anggaran dasar, dan akta notariil lainnya.'],
            ['category_name' => 'BILYET DEPOSITO', 'category_description' => 'Dokumen yang berkaitan dengan penerbitan dan pengelolaan bilyet deposito milik nasabah.'],
            ['category_name' => 'TABUNGAN', 'category_description' => 'Dokumen yang berkaitan dengan produk dan aktivitas tabungan, termasuk formulir, mutasi, dan informasi lainnya terkait rekening tabungan.'],
            ['category_name' => 'ANNUAL REPORT', 'category_description' => 'Laporan tahunan perusahaan yang memuat informasi kinerja keuangan, operasional, serta pencapaian perusahaan dalam satu tahun buku.'],
            ['category_name' => 'AUDIT', 'category_description' => 'Dokumen hasil pemeriksaan atau audit, baik dari auditor internal maupun eksternal, yang mencakup laporan, temuan, dan rekomendasi.'],
            ['category_name' => 'BANK INDONESIA', 'category_description' => 'Dokumen yang terkait dengan ketentuan, pelaporan, atau komunikasi resmi dengan Bank Indonesia.'],
            ['category_name' => 'PERATURAN DEKOM - PDK', 'category_description' => 'Dokumen yang memuat peraturan atau kebijakan Dewan Komisaris, termasuk Panitia Dewan Komisaris (PDK).'],
            ['category_name' => 'PERJANJIAN KERJASAMA', 'category_description' => 'Dokumen resmi yang memuat isi kesepakatan antara perusahaan dengan pihak ketiga, baik lembaga maupun individu, dalam bentuk perjanjian kerja sama.'],
            ['category_name' => 'POLIS & ASSURANSI', 'category_description' => 'Dokumen yang berkaitan dengan polis asuransi, klaim, dan perjanjian perlindungan asuransi lainnya, baik untuk nasabah maupun aset perusahaan.'],
            ['category_name' => 'RENCANA BISNIS BANK', 'category_description' => 'Dokumen yang berisi rencana strategis dan arah kebijakan bisnis bank dalam periode waktu tertentu, sesuai ketentuan regulator.'],
            ['category_name' => 'RKAP', 'category_description' => 'Dokumen perencanaan tahunan yang mencakup anggaran dan target operasional perusahaan dalam satu tahun fiskal.'],
            ['category_name' => 'Surat Edaran', 'category_description' => 'Surat resmi yang dikeluarkan oleh manajemen atau unit tertentu untuk menyampaikan kebijakan, informasi, atau instruksi kepada seluruh unit kerja.'],
            ['category_name' => 'Surat Dinas', 'category_description' => 'Surat formal yang digunakan dalam komunikasi kedinasan antarunit kerja di dalam perusahaan atau dengan instansi eksternal.'],
            ['category_name' => 'Memorandum of Understanding', 'category_description' => 'Dokumen yang memuat nota kesepahaman antara dua pihak atau lebih sebagai dasar kerja sama sebelum dituangkan dalam bentuk perjanjian yang mengikat.'],
            ['category_name' => 'Nota Dinas', 'category_description' => 'Dokumen komunikasi internal yang digunakan untuk menyampaikan permintaan, instruksi, atau pemberitahuan antarpegawai atau antarunit kerja.'],
            ['category_name' => 'Surat Kuasa Direksi', 'category_description' => 'Dokumen resmi yang berisi pelimpahan wewenang dari Direksi kepada pihak tertentu untuk melaksanakan tindakan hukum atau administratif atas nama Direksi.'],
            ['category_name' => 'Keputusan Dewan Komisaris', 'category_description' => 'Dokumen yang berisi keputusan resmi yang diambil oleh Dewan Komisaris dalam rangka pelaksanaan fungsi pengawasan dan pengambilan kebijakan strategis.'],
            ['category_name' => 'Standar Operasional Prosedur', 'category_description' => 'Dokumen yang berisi prosedur kerja baku yang harus diikuti dalam pelaksanaan suatu proses atau kegiatan untuk menjamin konsistensi dan kualitas.'],
            ['category_name' => 'Otoritas Jasa Keuangan', 'category_description' => 'Dokumen yang terkait dengan komunikasi, pelaporan, atau regulasi yang diterbitkan oleh atau ditujukan kepada Otoritas Jasa Keuangan.'],
            ['category_name' => 'Legalitas', 'category_description' => 'Dokumen-dokumen hukum yang menjamin status legal perusahaan, termasuk perizinan usaha, surat keputusan, dan dokumen legal lainnya.'],
            ['category_name' => 'Pajak', 'category_description' => 'Dokumen yang berkaitan dengan kewajiban perpajakan perusahaan, seperti laporan pajak, bukti setor, dan dokumen komunikasi dengan otoritas pajak.'],
            ['category_name' => 'Surat Perintah Kerja', 'category_description' => 'Dokumen resmi yang digunakan untuk memerintahkan pelaksanaan pekerjaan tertentu kepada pihak internal maupun eksternal, disertai dengan ruang lingkup dan tanggung jawab kerja.'],
            ['category_name' => 'Surat Keputusan', 'category_description' => 'Dokumen resmi berisi keputusan atau penetapan yang berlaku sebagai dasar administratif.'],
            ['category_name' => 'KEMENKUMHAM', 'category_description' => 'Dokumen yang berkaitan dengan urusan dan pelaporan kepada Kementerian Hukum dan HAM.'],
            ['category_name' => 'Jaminan Kredit', 'category_description' => 'Dokumen jaminan yang mendukung fasilitas kredit nasabah.'],
            ['category_name' => 'Dosir Kepegawaian', 'category_description' => 'Dokumen arsip personalia dan administrasi kepegawaian.'],
            ['category_name' => 'Minutes of Meeting (MoM)', 'category_description' => 'Dokumen ringkasan rapat dan tindak lanjut keputusan pertemuan.'],
        ];

        DB::table('categories')->upsert($categories, ['category_name'], ['category_description']);
    }
}
