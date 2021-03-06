<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 *  File Name       : Transaksi.php
 *  File Type       : Controller
 *  File Package    : CI_Controller
 ** * * * * * * * * * * * * * * * * * **
 *  Author          : Rizky Ardiansyah
 *  Date Created    : 22 Desember 2020
 */
class Transaksi extends CI_Controller
{
    private $smt_aktif;
    public function __construct()
    {
        parent::__construct();
        if ($this->session->has_userdata('username') == null) {
            $this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissible'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button> <h4><i class='icon fa fa-warning'></i> Alert!</h4> Harus Login Terlebih Dahulu</div>");
            redirect(base_url());
        }
        date_default_timezone_set('Asia/Jakarta');
        // $this->date = date('Y-m-d H:i:s');
        $this->smt_aktif = getSemesterAktif();
        $this->load->config('pdf_config');
        $this->load->library('fpdf');
        $this->load->library('terbilang');
        define('FPDF_FONTPATH', $this->config->item('fonts_path'));


        $this->load->model('M_cetak_kwitansi', 'cetak');
        $this->load->model('M_masterdata', 'masterdata');
        $this->load->model('M_transaksi', 'transaksi');
        $this->load->model('M_tunggakan', 'tunggakan');
        $this->load->model('M_aktivasi_mhs', 'aktivasi');
    }

    public function index()
    {
        // code here...
        $data['title'] = 'SiskeuNEW';
        $data['page'] = 'Transaksi';
        $data['content'] = 'transaksi/pembayaran';
        $where_date = [
            'tanggal' => date('Y-m-d')
        ];
        $data['jumlah_tx_hari_ini'] = $this->transaksi->getTxDateNow($where_date);
        $this->load->view('template', $data);
    }

    public function getDataTransaksi()
    {
        // code here...
        $smtAktifRes = $this->masterdata->getSemesterAktif()->row_array();
        $smtAktif = $smtAktifRes['id_smt'];
        $where = [
            'semester' => $smtAktif
        ];
        if ($this->input->is_ajax_request()) {
            $dataHistoriTx = $this->transaksi->getDataTransaksi($where)->result_array();
            $countHistoriTx = count($dataHistoriTx);
            for ($i = 0; $i < $countHistoriTx; $i++) {
                $resDetailTx = $this->transaksi->getDataTxDetail(['t.id_transaksi' => $dataHistoriTx[$i]['id_transaksi']])->result_array();
                $dataHistoriTx[$i]['detail_transaksi'] = $resDetailTx;
            }
            $data['data_transaksi'] = $dataHistoriTx;
            echo json_encode($data);
        } else {
            echo "Error";
        }
    }


    // Function Cari Data Mahasiswa
    public function Cari_Mhs()
    {
        if ($this->input->is_ajax_request()) {
            $dataKewajiban = [];
            $groupDataTx = [];

            $smtAktifRes = $this->masterdata->getSemesterAktif()->row_array();
            $smtAktif = $smtAktifRes['id_smt'];
            $nim = $this->input->post('nipd');
            $response = $this->masterdata->getMahasiswaByNim(['nipd' => $nim])->row_array();
            $dataMhs = $response;

            $where = 'id_jenis_pembayaran BETWEEN 8 AND 17';
            $resJnsPembayaran = $this->masterdata->GetJenisPembayaran($where)->result_array();


            if ($dataMhs != null) {
                $jenjang = $dataMhs['nm_jenj_didik'];
                $where_tahun = [
                    'angkatan' => $dataMhs['tahun_masuk']
                ];

                // cek tunggakan CS
                $dataCekTG = [
                    'nim' => $nim,
                    'jenis_tunggakan' => '1'
                ];
                $dataTG = $this->tunggakan->getTunggakanMhs($dataCekTG)->row_array();
                if ($dataTG != null) {
                    $dataKewajiban[] = [
                        'post_id' => 'bayar_TG',
                        'label' => 'Tunggakan',
                        'biaya' => $dataTG['jml_tunggakan']
                    ];
                } else {
                    $dataKewajiban[] = [
                        'post_id' => 'bayar_TG',
                        'label' => 'Tunggakan',
                        'biaya' => 0
                    ];
                }

                // cek tunggakan KMHS
                $dataCekTGKMHS = [
                    'nim' => $nim,
                    'jenis_tunggakan' => '5'
                ];
                $dataTG_KMHS = $this->tunggakan->getTunggakanMhs($dataCekTGKMHS)->row_array();
                if ($dataTG_KMHS != null) {
                    $dataKewajiban[] = [
                        'post_id' => 'bayar_TG_KMHS',
                        'label' => 'TG Kmhs',
                        'biaya' => $dataTG_KMHS['jml_tunggakan']
                    ];
                } else {
                    $dataKewajiban[] = [
                        'post_id' => 'bayar_TG_KMHS',
                        'label' => 'TG Kmhs',
                        'biaya' => 0
                    ];
                }

                $dataCekNim = [
                    'nim' => $nim,
                    'semester' => $smtAktif
                ];
                // cek histori transaksi
                $dataHistoriTx = $this->transaksi->getDataTransaksi($dataCekNim)->result_array();
                $countHistoriTx = count($dataHistoriTx);
                for ($i = 0; $i < $countHistoriTx; $i++) {
                    $resDetailTx = $this->transaksi->getDataTxDetail(['t.id_transaksi' => $dataHistoriTx[$i]['id_transaksi']])->result_array();
                    $dataHistoriTx[$i]['detail_transaksi'] = $resDetailTx;
                }
                $maxHistoriTx = $this->transaksi->cekMaxTransaksi($dataCekNim)->row_array();
                $dataBiaya = $this->masterdata->getBiayaAngkatan($where_tahun, $jenjang)->row_array();
                $biayaCS = $dataBiaya['cicilan_semester'] / 3;
                if ($dataHistoriTx != null) {
                    // ada histori transaksi
                    $maxDetailTx = $this->transaksi->cekMaxDetailTransaksi($maxHistoriTx['id_transaksi'])->row_array();
                    if ($maxDetailTx['id_jenis_pembayaran'] == 4) {
                        $C1 = [
                            'post_id' => 'bayar_C1',
                            'label' => 'Cicilan Ke-1',
                            'biaya' => 0
                        ];
                        $C2 = [
                            'post_id' => 'bayar_C2',
                            'label' => 'Cicilan Ke-2',
                            'biaya' => 0
                        ];
                        $C3 = [
                            'post_id' => 'bayar_C3',
                            'label' => 'Cicilan Ke-3',
                            'biaya' => 0
                        ];
                    } else if ($maxDetailTx['id_jenis_pembayaran'] == 3) {
                        $C1 = [
                            'post_id' => 'bayar_C1',
                            'label' => 'Cicilan Ke-1',
                            'biaya' => 0
                        ];
                        $C2 = [
                            'post_id' => 'bayar_C2',
                            'label' => 'Cicilan Ke-2',
                            'biaya' => 0
                        ];
                        $C3 = [
                            'post_id' => 'bayar_C3',
                            'label' => 'Cicilan Ke-3',
                            'biaya' => $biayaCS
                        ];
                    } else if ($maxDetailTx['id_jenis_pembayaran'] == 2) {
                        $C1 = [
                            'post_id' => 'bayar_C1',
                            'label' => 'Cicilan Ke-1',
                            'biaya' => 0
                        ];
                        $C2 = [
                            'post_id' => 'bayar_C2',
                            'label' => 'Cicilan Ke-2',
                            'biaya' => $biayaCS
                        ];
                        $C3 = [
                            'post_id' => 'bayar_C3',
                            'label' => 'Cicilan Ke-3',
                            'biaya' => $biayaCS
                        ];
                    } else if ($maxDetailTx['id_jenis_pembayaran'] == 5) {
                        $C1 = [
                            'post_id' => 'bayar_C1',
                            'label' => 'Cicilan Ke-1',
                            'biaya' => $biayaCS
                        ];
                        $C2 = [
                            'post_id' => 'bayar_C2',
                            'label' => 'Cicilan Ke-2',
                            'biaya' => $biayaCS
                        ];
                        $C3 = [
                            'post_id' => 'bayar_C3',
                            'label' => 'Cicilan Ke-3',
                            'biaya' => $biayaCS
                        ];
                    }

                    $dataKewajiban[] = $C1;
                    $dataKewajiban[] = $C2;
                    $dataKewajiban[] = $C3;

                    $countTotal = $dataKewajiban[0]['biaya'] + $dataKewajiban[1]['biaya'] + $dataKewajiban[2]['biaya'] + $dataKewajiban[3]['biaya'] + $dataKewajiban[4]['biaya'];
                    $dataMhs['totalKewajiban'] = $countTotal;
                    $dataMhs['dataKewajiban'] = $dataKewajiban;
                    $dataMhs['dataHistoriTX'] = $dataHistoriTx;
                    $dataMhs['jenis_pembayaran'] = $resJnsPembayaran;
                    echo json_encode($dataMhs);
                } else {
                    // belum ada histori transaksi

                    $kmhs = [
                        'post_id' => 'bayar_kmhs',
                        'label' => 'Kemahasiswaan',
                        'biaya' => $dataBiaya['kemahasiswaan']
                    ];
                    $dataKewajiban[] = $kmhs;

                    $C1 = [
                        'post_id' => 'bayar_C1',
                        'label' => 'Cicilan Ke-1',
                        'biaya' => $biayaCS
                    ];
                    $C2 = [
                        'post_id' => 'bayar_C2',
                        'label' => 'Cicilan Ke-2',
                        'biaya' => $biayaCS
                    ];
                    $C3 = [
                        'post_id' => 'bayar_C3',
                        'label' => 'Cicilan Ke-3',
                        'biaya' => $biayaCS
                    ];

                    $dataKewajiban[] = $C1;
                    $dataKewajiban[] = $C2;
                    $dataKewajiban[] = $C3;

                    $countTotal = $dataKewajiban[0]['biaya'] + $dataKewajiban[1]['biaya'] + $dataKewajiban[2]['biaya'] + $dataKewajiban[3]['biaya'] + $dataKewajiban[4]['biaya'] + $dataKewajiban[5]['biaya'];
                    $dataMhs['totalKewajiban'] = $countTotal;
                    $dataMhs['dataKewajiban'] = $dataKewajiban;
                    $dataMhs['dataHistoriTX'] = null;
                    $dataMhs['dataHistoriTxDetail'] = null;
                    $dataMhs['jenis_pembayaran'] = $resJnsPembayaran;
                    echo json_encode($dataMhs);
                }
            } else {
                echo json_encode($dataMhs);
            }
        } else {
            echo "Error";
        }
    }

    public function Proses_Bayar_Spp()
    {
        $smtAktifRes = $this->masterdata->getSemesterAktif()->row_array();
        $smtAktif = $smtAktifRes['id_smt'];
        $dataTxDetail = [];
        // get post()
        $nimMhs = $this->input->post('nim_mhs_bayar');
        $namaMhs = $this->input->post('nama_mhs_bayar');
        $jenjangMhs = $this->input->post('jenjang_mhs_bayar');
        $angkatanMhs = $this->input->post('angkatan_mhs_bayar');
        // =========== Data Pembayaran ========================
        $bayarTG = $this->input->post('bayar_TG');
        $bayarTG_KMHS = $this->input->post('bayar_TG_KMHS');
        // $bayarUB = $this->input->post('bayar_UB');
        $bayarKMHS = $this->input->post('bayar_kmhs');
        $bayarC1 = $this->input->post('bayar_C1');
        $bayarC2 = $this->input->post('bayar_C2');
        $bayarC3 = $this->input->post('bayar_C3');
        $totalBayar = $bayarTG + $bayarTG_KMHS + $bayarC1 + $bayarC2 + $bayarC3 + $bayarKMHS;
        // $All = $this->input->post();
        // var_dump($All);
        // die;
        $where_tahun = [
            'angkatan' => $angkatanMhs
        ];
        $dataBiaya = $this->masterdata->getBiayaAngkatan($where_tahun, $jenjangMhs)->row_array();
        $biayaCS = $dataBiaya['cicilan_semester'] / 3;
        $sisa_BayarC1 = $biayaCS - $bayarC1;
        $sisa_BayarC2 = $biayaCS - $bayarC2;
        $sisa_BayarC3 = $biayaCS - $bayarC3;
        $sisa_BayarKMHS = $dataBiaya['kemahasiswaan'] - $bayarKMHS;
        // var_dump($sisa_BayarC1 . '/' . $sisa_BayarC2 . '/' . $sisa_BayarC3);
        // die;

        //=============== cek data transaksi =================
        // cek histori transaksi
        $dataCek = [
            'nim' => $nimMhs,
            'semester' => $smtAktif
        ];
        $dataHistoriTx = $this->transaksi->getDataTransaksi($dataCek)->row_array();


        /*
        * *================ Fungsi genret id transaksi =================
        */
        $dateNow = date('Y-m-d H:i:s');
        $pecah_tgl_waktu = explode(' ', $dateNow);
        $tgl = $pecah_tgl_waktu[0];
        $jam = $pecah_tgl_waktu[1];
        $pecah_tgl = explode('-', $tgl);
        $tahunBerjalan = $pecah_tgl[0];
        $blnBerjalan = $pecah_tgl[1];
        // $tglBerjalan = $pecah_tgl[2];
        $cekTxId = $this->transaksi->cekTxId()->row_array();
        $ambil_id_tgl = substr($cekTxId['id_transaksi'], 4, -4);
        $id_date = $tahunBerjalan . $blnBerjalan;
        //jika belum ada id, di set id dengan format (tahun_tanggal_0001)
        $mulai_id = $this->createtxid->set(1, 4);
        if ($cekTxId['id_transaksi'] == 0) {
            $id_transaksi = $id_date . $mulai_id;
        }
        //jika tanggal di id_transaksi tidak sama dengan tanggal skrg, di set id dengan format (tahun_tanggal_0001)
        else if ($blnBerjalan != $ambil_id_tgl) {
            $id_transaksi = $id_date . $mulai_id;
        }
        //selain itu max(id_transaksi)+1
        else {
            $id_transaksi = $cekTxId['id_transaksi'] + 1;
        }
        // ============== End Fungsi genret id transaksi ===============


        /*
        * *=============== cek input tunggakan =========================
        */
        // ambil data tunggakan CS
        $whereCekNim = [
            'nim' => $nimMhs,
            'jenis_tunggakan' => 1
        ];
        $dataTG_CS = $this->tunggakan->getTunggakanMhs($whereCekNim)->row_array();
        $id_TGCS = [
            'id_tunggakan' => $dataTG_CS['id_tunggakan']
        ];
        if ($bayarTG != null) {
            $dataTGBaru = $dataTG_CS['jml_tunggakan'] - $bayarTG;
            if ($dataTGBaru === 0) {
                // hapus data tunggakan
                $tgDeleted = $this->tunggakan->deleteTunggakan($id_TGCS);
            } else {
                // update data tunggakan
                $dataUpdate = [
                    'jml_tunggakan' => $dataTGBaru
                ];
                $tgUpdated = $this->tunggakan->updateTunggakan($id_TGCS, $dataUpdate);
            }
            // add transaksi
            $dataTxDetail[] = [
                'id_transaksi' => $id_transaksi,
                'id_jenis_pembayaran' => 6,
                'jml_bayar' => $bayarTG
            ];
        }



        // ambil data tunggakan KMHS
        $CekTGKMHS = [
            'nim' => $nimMhs,
            'jenis_tunggakan' => 5
        ];
        $dataTG_KMHS = $this->tunggakan->getTunggakanMhs($CekTGKMHS)->row_array();
        $id_tgKMHS = [
            'id_tunggakan' => $dataTG_KMHS['id_tunggakan']
        ];
        if ($bayarTG_KMHS != null) {
            $dataTGKMHSBaru = $dataTG_KMHS['jml_tunggakan'] - $bayarTG_KMHS;
            if ($dataTGKMHSBaru === 0) {
                // hapus data tunggakan
                $tgDeleted = $this->tunggakan->deleteTunggakan($id_tgKMHS);
            } else {
                // update data tunggakan
                $dataUpdate = [
                    'jml_tunggakan' => $dataTGKMHSBaru
                ];
                $tgUpdated = $this->tunggakan->updateTunggakan($id_tgKMHS, $dataUpdate);
            }
            // add transaksi
            $dataTxDetail[] = [
                'id_transaksi' => $id_transaksi,
                'id_jenis_pembayaran' => 7,
                'jml_bayar' => $bayarTG_KMHS
            ];
        }

        // insert reg_mhs dan reg_ujian
        $dataAktifKRS = [
            'Tahun' => $smtAktif,
            'Identitas_ID' => '',
            'Jurusan_ID' => '',
            'NIM' => $nimMhs,
            'tgl_reg' => $tgl,
            'aktif' => 1
        ];
        $dataAktifUTS = [
            'tahun' => $smtAktif,
            'nim' => $nimMhs,
            'tgl_reg' => $tgl,
            'aktif' => 1,
            'keterangan' => ''
        ];
        $dataAktifUAS = [
            'tahun' => $smtAktif,
            'nim' => $nimMhs,
            'tgl_reg' => $tgl,
            'aktif' => 2,
            'keterangan' => ''
        ];
        // if ($sisa_BayarC1 < 500000 && $sisa_BayarC2 < 500000 && $sisa_BayarC3 < 500000) {
        //     // Aktifasi Perwalian, UTS, UAS
        //     $this->aktivasi->aktivasi_perwalian($dataAktifKRS);
        //     $this->aktivasi->aktivasi_ujian($dataAktifUTS);
        //     $this->aktivasi->aktivasi_ujian($dataAktifUAS);
        // } else if ($sisa_BayarC1 < 500000 && $sisa_BayarC2 < 500000) {
        //     // Aktifasi Perwalian, UTS
        //     $this->aktivasi->aktivasi_perwalian($dataAktifKRS);
        //     $this->aktivasi->aktivasi_ujian($dataAktifUTS);
        // } else if ($sisa_BayarC1 < 500000) {
        //     // Aktifasi Perwalian
        //     $this->aktivasi->aktivasi_perwalian($dataAktifKRS);
        // } else {
        //     if ($sisa_BayarC2 < 500000) {
        //         // Aktifasi UTS
        //         $this->aktivasi->aktivasi_ujian($dataAktifUTS);
        //     } else if ($sisa_BayarC3 < 500000) {
        //         // Aktifasi UAS
        //         $this->aktivasi->aktivasi_ujian($dataAktifUAS);
        //     }
        // }
        // die;

        if ($dataHistoriTx != null) {
            // ada data transaksi
            if ($bayarC1 != null) {
                if ($sisa_BayarC1 != 0) {
                    // bayar sebagian
                    if ($dataTG_CS != null) {
                        // update data tunggakan
                        $dataTGBaru = $dataTG_CS['jml_tunggakan'] + $sisa_BayarC1;
                        // update data tunggakan
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                    } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 1,
                            'jml_tunggakan' => $sisa_BayarC1,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                    }
                }
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 2,
                    'jml_bayar' => $bayarC1
                ];
            }


            /*
            * C2
            */
            if ($bayarC2 != null) {
                if ($sisa_BayarC2 != 0) {
                    // bayar sebagian
                    if ($dataTG_CS != null) {
                        // update data tunggakan
                        $dataTGBaru = $dataTG_CS['jml_tunggakan'] + $sisa_BayarC2;
                        // update data tunggakan
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                        // die;
                    } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 1,
                            'jml_tunggakan' => $sisa_BayarC2,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                    }
                }
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 3,
                    'jml_bayar' => $bayarC2
                ];
            }

            /*
            * C3
            */
            if ($bayarC3 != null) {
                if ($sisa_BayarC3 != 0) {
                    // bayar sebagian
                    if ($dataTG_CS != null) {
                        // update data tunggakan
                        $dataTGBaru = $dataTG_CS['jml_tunggakan'] + $sisa_BayarC3;
                        // update data tunggakan
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                        // die;
                    } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 1,
                            'jml_tunggakan' => $sisa_BayarC3,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                    }
                }
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 4,
                    'jml_bayar' => $bayarC3
                ];
            }
        } else {
            // tidak ada data transaksi
            if ($bayarKMHS != null) {
                if ($sisa_BayarKMHS != 0) {
                    // bayar sebagian
                    if ($dataTG_KMHS != null) {
                        // update data tunggakan
                        $dataTGKmhsBaru = $dataTG_KMHS['jml_tunggakan'] + $sisa_BayarKMHS;
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGKmhsBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($id_tgKMHS, $dataUpdate);
                    } else {
                        // if ($dataTG_KMHS != null) {
                        // update data tunggakan
                        //     $dataTGKmhsBaru = $dataTG_KMHS['jml_tunggakan'] + $sisa_BayarKMHS;
                        //     $dataUpdate = [
                        //         'jml_tunggakan' => $dataTGKmhsBaru
                        //     ];
                        //     $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                        // } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 5,
                            'jml_tunggakan' => $sisa_BayarKMHS,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                        // }
                    }
                }
                // bayar full
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 5,
                    'jml_bayar' => $bayarKMHS
                ];
            } else {
                if ($dataTG_KMHS != null) {
                    // update data tunggakan
                    $dataTGKmhsBaru = $dataTG_KMHS['jml_tunggakan'] + $sisa_BayarKMHS;
                    // var_dump($dataTGKmhsBaru);
                    // die;
                    $dataUpdate = [
                        'jml_tunggakan' => $dataTGKmhsBaru
                    ];
                    $tgUpdated = $this->tunggakan->updateTunggakan($id_tgKMHS, $dataUpdate);
                } else {
                    // add data tunggakan
                    $dataAddTG = [
                        'nim' => $nimMhs,
                        'jenis_tunggakan' => 5,
                        'jml_tunggakan' => $sisa_BayarKMHS,
                    ];
                    $this->tunggakan->addNewTunggakan($dataAddTG);
                }
            }

            if ($bayarC1 != null) {
                if ($sisa_BayarC1 != 0) {
                    // bayar sebagian
                    if ($dataTG_CS != null) {
                        // update data tunggakan
                        $dataTGBaru = $dataTG_CS['jml_tunggakan'] + $sisa_BayarC1;
                        // update data tunggakan
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                        // echo 'data tunggakan lama, rp.' . $dataTG_CS['jml_tunggakan'] . ', data Tunggakan baru Rp.' . $dataTGBaru;
                        // die;
                    } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 1,
                            'jml_tunggakan' => $sisa_BayarC1,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                        // echo 'add data tunggakan';
                        // die;
                    }
                }
                // else {
                //     // bayar full
                // }
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 2,
                    'jml_bayar' => $bayarC1
                ];
            }

            /*
            * C2
            */
            if ($bayarC2 != null) {
                if ($sisa_BayarC2 != 0) {
                    // bayar sebagian
                    if ($dataTG_CS != null) {
                        // update data tunggakan
                        $dataTGBaru = $dataTG_CS['jml_tunggakan'] + $sisa_BayarC2;
                        // update data tunggakan
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                        // echo 'data tunggakan lama, rp.' . $dataTG_CS['jml_tunggakan'] . ', data Tunggakan baru Rp.' . $dataTGBaru;
                        // die;
                    } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 1,
                            'jml_tunggakan' => $sisa_BayarC2,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                        // echo 'add data tunggakan';
                        // die;
                    }
                }
                // else {
                //     // bayar full
                // }
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 3,
                    'jml_bayar' => $bayarC2
                ];
            }

            /*
            * C3
            */
            if ($bayarC3 != null) {
                if ($sisa_BayarC3 != 0) {
                    // bayar sebagian
                    if ($dataTG_CS != null) {
                        // update data tunggakan
                        $dataTGBaru = $dataTG_CS['jml_tunggakan'] + $sisa_BayarC3;
                        // update data tunggakan
                        $dataUpdate = [
                            'jml_tunggakan' => $dataTGBaru
                        ];
                        $tgUpdated = $this->tunggakan->updateTunggakan($where_id, $dataUpdate);
                        // echo 'data tunggakan lama, rp.' . $dataTG_CS['jml_tunggakan'] . ', data Tunggakan baru Rp.' . $dataTGBaru;
                        // die;
                    } else {
                        // add data tunggakan
                        $dataAddTG = [
                            'nim' => $nimMhs,
                            'jenis_tunggakan' => 1,
                            'jml_tunggakan' => $sisa_BayarC3,
                        ];
                        $this->tunggakan->addNewTunggakan($dataAddTG);
                        // echo 'add data tunggakan';
                        // die;
                    }
                }
                // else {
                //     // bayar full
                // }
                $dataTxDetail[] = [
                    'id_transaksi' => $id_transaksi,
                    'id_jenis_pembayaran' => 4,
                    'jml_bayar' => $bayarC3
                ];
            }
        }



        $dataInsertTx = [
            'id_transaksi' => $id_transaksi,
            'tanggal' => $tgl,
            'jam' => $jam,
            'nim' => $nimMhs,
            'total_bayar' => $totalBayar,
            'semester' => $smtAktif,
            'user_id' => $this->session->userdata('id_user'),
            'status_transaksi' => 1
        ];

        // $inputDetailTx = count($dataTxDetail);
        // var_dump($inputDetailTx);
        // die;
        $insertTx = $this->transaksi->addNewTransaksi($dataInsertTx);
        if (!$insertTx) {
            // gagal
            $this->session->set_flashdata('message', '<div class="alert alert-danger" role="alert">Transaksi dengan id #' . $id_transaksi . ', Gagal di insert!</div>');
            redirect('transaksi', 'refresh');
        } else {
            $inputDetailTx = count($dataTxDetail);
            for ($i = 0; $i < $inputDetailTx; $i++) {
                $this->transaksi->addNewDetailTransaksi($dataTxDetail[$i]);
            }
            $this->session->set_flashdata('message', '<div class="alert alert-success" role="alert">Transaksi dengan id #' . $id_transaksi . ', berhasil!</div>');
            redirect('transaksi', 'refresh');
        }
    }
}
