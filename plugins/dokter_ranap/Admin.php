<?php
namespace Plugins\Dokter_Ranap;

use Systems\AdminModule;

class Admin extends AdminModule
{

    public function navigation()
    {
        return [
            'Kelola'   => 'manage',
        ];
    }

    public function anyManage()
    {
        $tgl_masuk = '';
        $tgl_masuk_akhir = '';
        $status_pulang = '';
        $status_periksa = '';

        if(isset($_POST['periode_rawat_inap'])) {
          $tgl_masuk = $_POST['periode_rawat_inap'];
        }
        if(isset($_POST['periode_rawat_inap_akhir'])) {
          $tgl_masuk_akhir = $_POST['periode_rawat_inap_akhir'];
        }
        if(isset($_POST['status_pulang'])) {
          $status_pulang = $_POST['status_pulang'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        $cek_vclaim = $this->db('mlite_modules')->where('dir', 'vclaim')->oneArray();
        $this->_Display($tgl_masuk, $tgl_masuk_akhir, $status_pulang, $status_periksa);
        return $this->draw('manage.html', ['rawat_inap' => $this->assign, 'cek_vclaim' => $cek_vclaim]);
    }

    public function anyDisplay()
    {
        $tgl_masuk = '';
        $tgl_masuk_akhir = '';
        $status_pulang = '';
        $status_periksa = '';

        if(isset($_POST['periode_rawat_inap'])) {
          $tgl_masuk = $_POST['periode_rawat_inap'];
        }
        if(isset($_POST['periode_rawat_inap_akhir'])) {
          $tgl_masuk_akhir = $_POST['periode_rawat_inap_akhir'];
        }
        if(isset($_POST['status_pulang'])) {
          $status_pulang = $_POST['status_pulang'];
        }
        if(isset($_POST['status_periksa'])) {
          $status_periksa = $_POST['status_periksa'];
        }
        $this->_Display($tgl_masuk, $tgl_masuk_akhir, $status_pulang, $status_periksa);
        echo $this->draw('display.html', ['rawat_inap' => $this->assign]);
        exit();
    }

    public function _Display($tgl_masuk='', $tgl_masuk_akhir='', $status_pulang='', $status_periksa='')
    {
        $this->_addHeaderFiles();

        $this->assign['kamar'] = $this->db('kamar')->join('bangsal', 'bangsal.kd_bangsal=kamar.kd_bangsal')->where('statusdata', '1')->toArray();
        $this->assign['dokter']         = $this->db('dokter')->where('status', '1')->toArray();
        $this->assign['penjab']       = $this->db('penjab')->where('status', '1')->toArray();
        $this->assign['no_rawat'] = '';
        $username = $this->core->getUserInfo('username', null, true);

        $bangsal = str_replace(",","','", $this->core->getUserInfo('cap', null, true));

        $sql = "SELECT
            kamar_inap.*,
            reg_periksa.*,
            pasien.*,
            kamar.*,
            bangsal.*,
            penjab.*,
            dpjp_ranap.*
          FROM
            kamar_inap,
            reg_periksa,
            pasien,
            kamar,
            bangsal,
            penjab,
            dpjp_ranap
          WHERE
            kamar_inap.no_rawat=reg_periksa.no_rawat
          AND
            kamar_inap.no_rawat=dpjp_ranap.no_rawat
          AND
            reg_periksa.no_rkm_medis=pasien.no_rkm_medis
          AND
            kamar_inap.kd_kamar=kamar.kd_kamar
          AND
            bangsal.kd_bangsal=kamar.kd_bangsal
          AND
            reg_periksa.kd_pj=penjab.kd_pj";

        if ($this->core->getUserInfo('role') != 'admin') {
          $sql .= " AND dpjp_ranap.kd_dokter = '$username'";
        }
        if($status_pulang == '') {
          $sql .= " AND kamar_inap.stts_pulang = '-'";
        }
        if($status_pulang == 'all' && $tgl_masuk !== '' && $tgl_masuk_akhir !== '') {
          $sql .= " AND kamar_inap.stts_pulang = '-' AND kamar_inap.tgl_masuk BETWEEN '$tgl_masuk' AND '$tgl_masuk_akhir'";
        }
        if($status_pulang == 'masuk' && $tgl_masuk !== '' && $tgl_masuk_akhir !== '') {
          $sql .= " AND kamar_inap.tgl_masuk BETWEEN '$tgl_masuk' AND '$tgl_masuk_akhir'";
        }
        if($status_pulang == 'pulang' && $tgl_masuk !== '' && $tgl_masuk_akhir !== '') {
          $sql .= " AND kamar_inap.tgl_keluar BETWEEN '$tgl_masuk' AND '$tgl_masuk_akhir'";
        }
        if($status_periksa == 'lunas' && $status_pulang == '-' && $tgl_masuk !== '' && $tgl_masuk_akhir !== '') {
          $sql .= " AND reg_periksa.status_bayar = 'Sudah Bayar' AND kamar_inap.tgl_masuk BETWEEN '$tgl_masuk' AND '$tgl_masuk_akhir'";
        }
        $sql .= " GROUP BY reg_periksa.no_rawat";

        $stmt = $this->db()->pdo()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $this->assign['list'] = [];
        foreach ($rows as $row) {
          $row['status_billing'] = 'Sudah Bayar';
          $get_billing = $this->db('mlite_billing')->where('no_rawat', $row['no_rawat'])->like('kd_billing', 'RI%')->oneArray();
          if(empty($get_billing['kd_billing'])) {
            $row['kd_billing'] = 'RI.'.date('d.m.Y.H.i.s');
            $row['tgl_billing'] = date('Y-m-d H:i');
            $row['status_billing'] = 'Belum Bayar';
          }
          $dpjp_ranap = $this->db('dpjp_ranap')
            ->join('dokter', 'dokter.kd_dokter=dpjp_ranap.kd_dokter')
            ->where('no_rawat', $row['no_rawat'])
            ->toArray();
          $row['dokter'] = $dpjp_ranap;
          $this->assign['list'][] = $row;
        }

    }

    public function postSaveDetail()
    {
      if($_POST['kat'] == 'tindakan') {
        $jns_perawatan = $this->db('jns_perawatan_inap')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->oneArray();
        $this->db('rawat_inap_dr')->save([
          'no_rawat' => $_POST['no_rawat'],
          'kd_jenis_prw' => $_POST['kd_jenis_prw'],
          'kd_dokter' => $this->core->getUserInfo('username', null, true),
          'tgl_perawatan' => $_POST['tgl_perawatan'],
          'jam_rawat' => $_POST['jam_rawat'],
          'material' => $jns_perawatan['material'],
          'bhp' => $jns_perawatan['bhp'],
          'tarif_tindakandr' => $jns_perawatan['tarif_tindakandr'],
          'kso' => $jns_perawatan['kso'],
          'menejemen' => $jns_perawatan['menejemen'],
          'biaya_rawat' => $jns_perawatan['total_byrdr']
        ]);
      }
      if($_POST['kat'] == 'obat') {

        $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);
        $cek_resep = $this->db('resep_obat')->where('no_rawat', $_POST['no_rawat'])->where('tgl_peresepan', $_POST['tgl_perawatan'])->where('tgl_perawatan', '0000-00-00')->where('status', 'ranap')->oneArray();

        if(empty($cek_resep)) {

          $resep_obat = $this->db('resep_obat')
            ->save([
              'no_resep' => $no_resep,
              'tgl_perawatan' => '0000-00-00',
              'jam' => '00:00:00',
              'no_rawat' => $_POST['no_rawat'],
              'kd_dokter' => $this->core->getUserInfo('username', null, true),
              'tgl_peresepan' => $_POST['tgl_perawatan'],
              'jam_peresepan' => $_POST['jam_rawat'],
              'status' => 'ranap',
              'tgl_penyerahan' => '0000-00-00',
              'jam_penyerahan' => '00:00:00'
            ]);

          if ($this->db('resep_obat')->where('no_resep', $no_resep)->where('kd_dokter', $this->core->getUserInfo('username', null, true))->oneArray()) {
            $this->db('resep_dokter')
              ->save([
                'no_resep' => $no_resep,
                'kode_brng' => $_POST['kd_jenis_prw'],
                'jml' => $_POST['jml'],
                'aturan_pakai' => $_POST['aturan_pakai']
              ]);
          }

        } else {

          $no_resep = $cek_resep['no_resep'];
          $this->db('resep_dokter')
            ->save([
              'no_resep' => $no_resep,
              'kode_brng' => $_POST['kd_jenis_prw'],
              'jml' => $_POST['jml'],
              'aturan_pakai' => $_POST['aturan_pakai']
            ]);

        }

      }

      if($_POST['kat'] == 'racikan') {

        $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);
        $cek_resep = $this->db('resep_obat')->where('no_rawat', $_POST['no_rawat'])->where('tgl_peresepan', $_POST['tgl_perawatan'])->where('tgl_perawatan', '0000-00-00')->where('status', 'ranap')->oneArray();

        $_POST['jam_rawat'] = date('H:i:s');

        if(empty($cek_resep)) {

          $resep_obat = $this->db('resep_obat')
            ->save([
              'no_resep' => $no_resep,
              'tgl_perawatan' => '0000-00-00',
              'jam' => '00:00:00',
              'no_rawat' => $_POST['no_rawat'],
              'kd_dokter' => $this->core->getUserInfo('username', null, true),
              'tgl_peresepan' => $_POST['tgl_perawatan'],
              'jam_peresepan' => $_POST['jam_rawat'],
              'status' => 'ranap',
              'tgl_penyerahan' => '0000-00-00',
              'jam_penyerahan' => '00:00:00'
            ]);

          if ($this->db('resep_obat')->where('no_resep', $no_resep)->where('kd_dokter', $this->core->getUserInfo('username', null, true))->oneArray()) {
            $no_racik = $this->db('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
            $no_racik = $no_racik+1;
            $this->db('resep_dokter_racikan')
              ->save([
                'no_resep' => $no_resep,
                'no_racik' => $no_racik,
                'nama_racik' => $_POST['nama_racik'],
                'kd_racik' => $_POST['kd_jenis_prw'],
                'jml_dr' => $_POST['jml'],
                'aturan_pakai' => $_POST['aturan_pakai'],
                'keterangan' => $_POST['keterangan']
              ]);
            $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
            $_POST['kandungan'] = json_decode($_POST['kandungan'], true);
            for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
              $kapasitas = $this->db('databarang')->where('kode_brng', $_POST['kode_brng'][$i]['value'])->oneArray();
              $jml = $_POST['jml']*$_POST['kandungan'][$i]['value'];
              $jml = round(($jml/$kapasitas['kapasitas']),1);
              $this->db('resep_dokter_racikan_detail')
                ->save([
                  'no_resep' => $no_resep,
                  'no_racik' => $no_racik,
                  'kode_brng' => $_POST['kode_brng'][$i]['value'],
                  'p1' => '1',
                  'p2' => '1',
                  'kandungan' => $_POST['kandungan'][$i]['value'],
                  'jml' => $jml
                ]);
            }
          }

        } else {

          $no_resep = $cek_resep['no_resep'];

          $no_racik = $this->db('resep_dokter_racikan')->where('no_resep', $no_resep)->count();
          $no_racik = $no_racik+1;
          $this->db('resep_dokter_racikan')
            ->save([
              'no_resep' => $no_resep,
              'no_racik' => $no_racik,
              'nama_racik' => $_POST['nama_racik'],
              'kd_racik' => $_POST['kd_jenis_prw'],
              'jml_dr' => $_POST['jml'],
              'aturan_pakai' => $_POST['aturan_pakai'],
              'keterangan' => $_POST['keterangan']
            ]);
          $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
          $_POST['kandungan'] = json_decode($_POST['kandungan'], true);
          for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
            $kapasitas = $this->db('databarang')->where('kode_brng', $_POST['kode_brng'][$i]['value'])->oneArray();
            $jml = $_POST['jml']*$_POST['kandungan'][$i]['value'];
            $jml = round(($jml/$kapasitas['kapasitas']),1);
            $this->db('resep_dokter_racikan_detail')
              ->save([
                'no_resep' => $no_resep,
                'no_racik' => $no_racik,
                'kode_brng' => $_POST['kode_brng'][$i]['value'],
                'p1' => '1',
                'p2' => '1',
                'kandungan' => $_POST['kandungan'][$i]['value'],
                'jml' => $jml
              ]);
          }

        }
      }

      if($_POST['kat'] == 'laboratorium') {
        $cek_lab = $this->db('permintaan_lab')->where('no_rawat', $_POST['no_rawat'])->where('tgl_permintaan', date('Y-m-d'))->where('tgl_sampel', '<>', '0000-00-00')->where('status', 'ranap')->oneArray();
        if(!$cek_lab) {
          $max_id = $this->db('permintaan_lab')->select(['noorder' => 'ifnull(MAX(CONVERT(RIGHT(noorder,4),signed)),0)'])->where('tgl_permintaan', date('Y-m-d'))->oneArray();
          if(empty($max_id['noorder'])) {
            $max_id['noorder'] = '0000';
          }
          $_next_noorder = sprintf('%04s', ($max_id['noorder'] + 1));
          $noorder = 'PL'.date('Ymd').''.$_next_noorder;

          $permintaan_lab = $this->db('permintaan_lab')
            ->save([
              'noorder' => $noorder,
              'no_rawat' => $_POST['no_rawat'],
              'tgl_permintaan' => $_POST['tgl_perawatan'],
              'jam_permintaan' => $_POST['jam_rawat'],
              'tgl_sampel' => '0000-00-00',
              'jam_sampel' => '00:00:00',
              'tgl_hasil' => '0000-00-00',
              'jam_hasil' => '00:00:00',
              'dokter_perujuk' => $this->core->getUserInfo('username', null, true),
              'status' => 'ranap',
              'informasi_tambahan' => $_POST['informasi_tambahan'],
              'diagnosa_klinis' => $_POST['diagnosa_klinis']
            ]);
          $this->db('permintaan_pemeriksaan_lab')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
          $template_laboratorium = $this->db('template_laboratorium')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->toArray();
          for ($i = 0; $i < count($template_laboratorium); $i++) {
            $this->db('permintaan_detail_permintaan_lab')
              ->save([
                'noorder' => $noorder,
                'kd_jenis_prw' => $_POST['kd_jenis_prw'],
                'id_template' => $template_laboratorium[$i]['id_template'],
                'stts_bayar' => 'Belum'
              ]);
          }

        } else {
          $noorder = $cek_lab['noorder'];
          $this->db('permintaan_pemeriksaan_lab')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
          $template_laboratorium = $this->db('template_laboratorium')->where('kd_jenis_prw', $_POST['kd_jenis_prw'])->toArray();
          for ($i = 0; $i < count($template_laboratorium); $i++) {
            $this->db('permintaan_detail_permintaan_lab')
              ->save([
                'noorder' => $noorder,
                'kd_jenis_prw' => $_POST['kd_jenis_prw'],
                'id_template' => $template_laboratorium[$i]['id_template'],
                'stts_bayar' => 'Belum'
              ]);
          }
        }
      }

      if($_POST['kat'] == 'radiologi') {
        $cek_rad = $this->db('permintaan_radiologi')->where('no_rawat', $_POST['no_rawat'])->where('tgl_permintaan', date('Y-m-d'))->where('tgl_sampel', '<>', '0000-00-00')->where('status', 'ranap')->oneArray();
        if(!$cek_rad) {
          $max_id = $this->db('permintaan_radiologi')->select(['noorder' => 'ifnull(MAX(CONVERT(RIGHT(noorder,4),signed)),0)'])->where('tgl_permintaan', date('Y-m-d'))->oneArray();
          if(empty($max_id['noorder'])) {
            $max_id['noorder'] = '0000';
          }
          $_next_noorder = sprintf('%04s', ($max_id['noorder'] + 1));
          $noorder = 'PR'.date('Ymd').''.$_next_noorder;

          $permintaan_rad = $this->db('permintaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'no_rawat' => $_POST['no_rawat'],
              'tgl_permintaan' => $_POST['tgl_perawatan'],
              'jam_permintaan' => $_POST['jam_rawat'],
              'tgl_sampel' => '0000-00-00',
              'jam_sampel' => '00:00:00',
              'tgl_hasil' => '0000-00-00',
              'jam_hasil' => '00:00:00',
              'dokter_perujuk' => $this->core->getUserInfo('username', null, true),
              'status' => 'ranap',
              'informasi_tambahan' => $_POST['informasi_tambahan'],
              'diagnosa_klinis' => $_POST['diagnosa_klinis']
            ]);
          $this->db('permintaan_pemeriksaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);

        } else {
          $noorder = $cek_rad['noorder'];
          $this->db('permintaan_pemeriksaan_radiologi')
            ->save([
              'noorder' => $noorder,
              'kd_jenis_prw' => $_POST['kd_jenis_prw'],
              'stts_bayar' => 'Belum'
            ]);
        }
      }

      exit();
    }

    public function postHapusDetail()
    {
      if($_POST['provider'] == 'rawat_inap_dr') {
        $this->db('rawat_inap_dr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      if($_POST['provider'] == 'rawat_inap_pr') {
        $this->db('rawat_inap_pr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      if($_POST['provider'] == 'rawat_inap_drpr') {
        $this->db('rawat_inap_drpr')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('tgl_perawatan', $_POST['tgl_perawatan'])
        ->where('jam_rawat', $_POST['jam_rawat'])
        ->delete();
      }
      exit();
    }

    public function postHapusNomorPermintaanLaboratorium()
    {
      $this->db('permintaan_lab')
      ->where('no_rawat', $_POST['no_rawat'])
      ->where('noorder', $_POST['noorder'])
      ->where('tgl_permintaan', $_POST['tgl_permintaan'])
      ->where('jam_permintaan', $_POST['jam_permintaan'])
      ->where('status', 'Ranap')
      ->delete();
      exit();
    }

    public function postHapusPermintaanLab()
    {
      $this->db('permintaan_lab')
      ->where('noorder', $_POST['noorder'])
      ->where('no_rawat', $_POST['no_rawat'])
      ->delete();
      exit();
    }

    public function postHapusPermintaanLaboratorium()
    {
      $this->db('permintaan_pemeriksaan_lab')
      ->where('noorder', $_POST['noorder'])
      ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
      ->where('stts_bayar', 'Belum')
      ->delete();
      exit();
    }

    public function getDetailPermintaan($noorder, $kd_jenis_prw)
    {
      $rows = $this->db('permintaan_detail_permintaan_lab')->where('noorder', $noorder)->where('kd_jenis_prw', $kd_jenis_prw)->toArray();
      $detail_permintaan_lab = [];
      foreach ($rows as $row) {
        $row['template_laboratorium'] = $this->db('template_laboratorium')->where('kd_jenis_prw', $row['kd_jenis_prw'])->where('id_template', $row['id_template'])->oneArray();
        $detail_permintaan_lab[] = $row;
      }
      $this->tpl->set('detail', $detail_permintaan_lab);
      echo $this->tpl->draw(MODULES.'/dokter_ranap/view/admin/details.html', true);
      exit();
    }

    public function postHapusDetailPermintaan()
    {
      $this->db('permintaan_detail_permintaan_lab')
        ->where('noorder', $_POST['noorder'])
        ->where('kd_jenis_prw', $_POST['kd_jenis_prw'])
        ->where('id_template', $_POST['id_template'])
        ->delete();
      exit();
    }

    public function postHapusResep()
    {
      if(isset($_POST['kd_jenis_prw'])) {
        $this->db('resep_dokter')
        ->where('no_resep', $_POST['no_resep'])
        ->where('kode_brng', $_POST['kd_jenis_prw'])
        ->delete();
      } else {
        $this->db('resep_obat')
        ->where('no_resep', $_POST['no_resep'])
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('tgl_peresepan', $_POST['tgl_peresepan'])
        ->where('jam_peresepan', $_POST['jam_peresepan'])
        ->delete();
      }

      exit();
    }

    public function anyCopyResep()
    {
      $return = $this->db('resep_dokter')
        ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
        ->join('gudangbarang', 'gudangbarang.kode_brng=resep_dokter.kode_brng')
        ->where('kd_bangsal', $this->settings->get('farmasi.deporanap'))
        ->where('no_resep', $_POST['no_resep'])
        ->toArray();
      echo $this->draw('copyresep.display.html', ['copy_resep' => $return]);
      exit();
    }

    public function postSaveCopyResep()
    {
      $_POST['kode_brng'] = json_decode($_POST['kode_brng'], true);
      $_POST['jml'] = json_decode($_POST['jml'], true);
      $_POST['aturan_pakai'] = json_decode($_POST['aturan_pakai'], true);

      $no_resep = $this->core->setNoResep($_POST['tgl_perawatan']);

      $resep_obat = $this->db('resep_obat')
        ->save([
          'no_resep' => $no_resep,
          'tgl_perawatan' => '0000-00-00',
          'jam' => '00:00:00',
          'no_rawat' => $_POST['no_rawat'],
          'kd_dokter' => $this->core->getUserInfo('username', null, true),
          'tgl_peresepan' => $_POST['tgl_perawatan'],
          'jam_peresepan' => $_POST['jam_rawat'],
          'status' => 'ranap',
          'tgl_penyerahan' => '0000-00-00',
          'jam_penyerahan' => '00:00:00'
        ]);

      for ($i = 0; $i < count($_POST['kode_brng']); $i++) {
        /*$cek_stok = $this->db('gudangbarang')
          ->join('databarang', 'databarang.kode_brng=gudangbarang.kode_brng')
          ->where('gudangbarang.kode_brng', $_POST['kode_brng'][$i]['value'])
          ->where('kd_bangsal', $this->settings->get('farmasi.deporanap'))
          ->oneArray();*/

        //if($cek_stok['stok'] < $cek_stok['stokminimal']) {
        //  echo "Error";
        //} else {
          $this->db('resep_dokter')
            ->save([
              'no_resep' => $no_resep,
              'kode_brng' => $_POST['kode_brng'][$i]['value'],
              'jml' => $_POST['jml'][$i]['value'],
              'aturan_pakai' => $_POST['aturan_pakai'][$i]['value']
            ]);
        //}

      }

      exit();
    }

    public function anyRincian()
    {
      $rows_rawat_inap_dr = $this->db('rawat_inap_dr')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $rows_rawat_inap_pr = $this->db('rawat_inap_pr')->where('no_rawat', $_POST['no_rawat'])->toArray();
      $rows_rawat_inap_drpr = $this->db('rawat_inap_drpr')->where('no_rawat', $_POST['no_rawat'])->toArray();

      $jumlah_total = 0;
      $rawat_inap_dr = [];
      $rawat_inap_pr = [];
      $rawat_inap_drpr = [];
      $i = 1;

      if($rows_rawat_inap_dr) {
        foreach ($rows_rawat_inap_dr as $row) {
          $jns_perawatan = $this->db('jns_perawatan_inap')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_inap_dr';
          $rawat_inap_dr[] = $row;
        }
      }

      if($rows_rawat_inap_pr) {
        foreach ($rows_rawat_inap_pr as $row) {
          $jns_perawatan = $this->db('jns_perawatan_inap')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_inap_pr';
          $rawat_inap_pr[] = $row;
        }
      }

      if($rows_rawat_inap_drpr) {
        foreach ($rows_rawat_inap_drpr as $row) {
          $jns_perawatan = $this->db('jns_perawatan_inap')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $jumlah_total = $jumlah_total + $row['biaya_rawat'];
          $row['provider'] = 'rawat_inap_drpr';
          $rawat_inap_drpr[] = $row;
        }
      }

      $rows = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter', 'resep_dokter.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('resep_obat.status', 'ranap')
        ->group('resep_dokter.no_resep')
        ->toArray();
      $resep = [];
      $jumlah_total_resep = 0;
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter'] = $this->db('resep_dokter')->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter'] as $value) {
          $value['dasar'] = $value['jml'] * $value['dasar'];
          $jumlah_total_resep += floatval($value['dasar']);
        }
        $resep[] = $row;
      }

      $rows_racikan = $this->db('resep_obat')
        ->join('dokter', 'dokter.kd_dokter=resep_obat.kd_dokter')
        ->join('resep_dokter_racikan', 'resep_dokter_racikan.no_resep=resep_obat.no_resep')
        ->where('no_rawat', $_POST['no_rawat'])
        ->group('resep_dokter_racikan.no_resep')
        ->where('resep_obat.status', 'ranap')
        ->toArray();
      $resep_racikan = [];
      $jumlah_total_resep_racikan = 0;
      foreach ($rows_racikan as $row) {
        $row['nomor'] = $i++;
        $row['resep_dokter_racikan_detail'] = $this->db('resep_dokter_racikan_detail')->join('databarang', 'databarang.kode_brng=resep_dokter_racikan_detail.kode_brng')->where('no_resep', $row['no_resep'])->toArray();
        foreach ($row['resep_dokter_racikan_detail'] as $value) {
          $value['dasar'] = $value['jml'] * $value['dasar'];
          $jumlah_total_resep_racikan += floatval($value['dasar']);
        }
        $resep_racikan[] = $row;
      }

      // $rows_laboratorium = $this->db('permintaan_lab')
      //   ->join('permintaan_pemeriksaan_lab', 'permintaan_pemeriksaan_lab.noorder=permintaan_lab.noorder')
      //   ->where('no_rawat', $_POST['no_rawat'])
      //   ->where('permintaan_lab.status', 'ranap')
      //   ->toArray();
      // $jumlah_total_lab = 0;
      // $laboratorium = [];

      // if($rows_laboratorium) {
      //   foreach ($rows_laboratorium as $row) {
      //     $jns_perawatan = $this->db('jns_perawatan_lab')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
      //     $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
      //     $row['kelas'] = $jns_perawatan['kelas'];
      //     $row['total_byr'] = $jns_perawatan['total_byr'];
      //     $jumlah_total_lab += $jns_perawatan['total_byr'];
      //     $laboratorium[] = $row;
      //   }
      // }

      $rows_laboratorium = $this->db('permintaan_lab')
      ->join('dokter', 'dokter.kd_dokter=permintaan_lab.dokter_perujuk')
      ->where('no_rawat', $_POST['no_rawat'])
      ->where('permintaan_lab.status', 'ranap')
      ->toArray();
      $jumlah_total_lab = 0;
      $laboratorium = [];
      foreach ($rows_laboratorium as $row) {
        $rows2 = $this->db('permintaan_pemeriksaan_lab')
          ->join('jns_perawatan_lab', 'jns_perawatan_lab.kd_jenis_prw=permintaan_pemeriksaan_lab.kd_jenis_prw')
          //->join('permintaan_detail_permintaan_lab', 'permintaan_detail_permintaan_lab.noorder=permintaan_pemeriksaan_lab.noorder')
          ->where('permintaan_pemeriksaan_lab.noorder', $row['noorder'])
          ->toArray();
          $row['permintaan_pemeriksaan_lab'] = [];
          foreach ($rows2 as $row2) {
            $row2['noorder'] = $row2['noorder'];
            $row2['kd_jenis_prw'] = $row2['kd_jenis_prw'];
            $row2['stts_bayar'] = $row2['stts_bayar'];
            $row2['nm_perawatan'] = $row2['nm_perawatan'];
            $row2['kd_pj'] = $row2['kd_pj'];
            $row2['status'] = $row2['status'];
            $row2['kelas'] = $row2['kelas'];
            $row2['kategori'] = $row2['kategori'];
            $rows3 = $this->db('permintaan_detail_permintaan_lab')->where('noorder', $row2['noorder'])->where('kd_jenis_prw', $row2['kd_jenis_prw'])->toArray();
            $row2['permintaan_detail_permintaan_lab'] = [];
            foreach ($rows3 as $row3) {
              $row3['template_laboratorium'] = $this->db('template_laboratorium')->where('kd_jenis_prw', $row3['kd_jenis_prw'])->where('id_template', $row3['id_template'])->oneArray();
              $row2['permintaan_detail_permintaan_lab'][] = $row3;
            }
            $row['permintaan_pemeriksaan_lab'][] = $row2;
          }
        $laboratorium[] = $row;
      }      

      $rows_radiologi = $this->db('permintaan_radiologi')
        ->join('permintaan_pemeriksaan_radiologi', 'permintaan_pemeriksaan_radiologi.noorder=permintaan_radiologi.noorder')
        ->where('no_rawat', $_POST['no_rawat'])
        ->where('permintaan_radiologi.status', 'ranap')
        ->toArray();
      $jumlah_total_rad = 0;
      $radiologi = [];

      if($rows_radiologi) {
        foreach ($rows_radiologi as $row) {
          $jns_perawatan = $this->db('jns_perawatan_radiologi')->where('kd_jenis_prw', $row['kd_jenis_prw'])->oneArray();
          $row['nm_perawatan'] = $jns_perawatan['nm_perawatan'];
          $row['kelas'] = $jns_perawatan['kelas'];
          $row['total_byr'] = $jns_perawatan['total_byr'];
          $jumlah_total_rad += $jns_perawatan['total_byr'];
          $radiologi[] = $row;
        }
      }

      $reg_periksa = $this->db('reg_periksa')->where('no_rawat', $_POST['no_rawat'])->oneArray();
      $rows_data_resep = $this->db('resep_obat')
      ->join('reg_periksa', 'reg_periksa.no_rawat=resep_obat.no_rawat')
      ->where('resep_obat.kd_dokter', $this->core->getUserInfo('username', null, true))
      ->where('reg_periksa.no_rkm_medis', $reg_periksa['no_rkm_medis'])
      ->toArray();

      $data_resep = [];
      foreach ($rows_data_resep as $row) {
        $row['resep_dokter'] = $this->db('resep_dokter')
          ->join('databarang', 'databarang.kode_brng=resep_dokter.kode_brng')
          ->where('no_resep', $row['no_resep'])
          ->toArray();
        $data_resep[] = $row;
      }

      echo $this->draw('rincian.html', [
        'rawat_inap_dr' => $rawat_inap_dr,
        'rawat_inap_pr' => $rawat_inap_pr,
        'rawat_inap_drpr' => $rawat_inap_drpr,
        'resep' => $resep,
        'resep_racikan' => $resep_racikan,
        'data_resep' => $data_resep,
        'laboratorium' => $laboratorium,
        'radiologi' => $radiologi,
        'jumlah_total' => $jumlah_total,
        'jumlah_total_resep' => $jumlah_total_resep,
        'jumlah_total_resep_racikan' => $jumlah_total_resep_racikan,
        'jumlah_total_lab' => $jumlah_total_lab,
        'jumlah_total_rad' => $jumlah_total_rad,
        'no_rawat' => $_POST['no_rawat']
      ]);
      exit();
    }

    public function anySoap()
    {
      $prosedurs = $this->db('prosedur_pasien')
       ->where('no_rawat', $_POST['no_rawat'])
       ->asc('prioritas')
       ->toArray();
      $prosedur = [];
      foreach ($prosedurs as $row) {
       $icd9 = $this->db('icd9')->where('kode', $row['kode'])->oneArray();
       $row['nama'] = $icd9['deskripsi_panjang'];
       $prosedur[] = $row;
      }
      $diagnosas = $this->db('diagnosa_pasien')
       ->where('no_rawat', $_POST['no_rawat'])
       ->asc('prioritas')
       ->toArray();
      $diagnosa = [];
      foreach ($diagnosas as $row) {
       $icd10 = $this->db('penyakit')->where('kd_penyakit', $row['kd_penyakit'])->oneArray();
       $row['nama'] = $icd10['nm_penyakit'];
       $diagnosa[] = $row;
      }

      $i = 1;
      $row['nama_petugas'] = '';
      $row['departemen_petugas'] = '';
      $rows = $this->db('pemeriksaan_ralan')
       ->where('no_rawat', $_POST['no_rawat'])
       ->toArray();
      $result = [];
      foreach ($rows as $row) {
       $row['nomor'] = $i++;
       $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
       $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
       $result[] = $row;
      }

      $rows_ranap = $this->db('pemeriksaan_ranap')
       ->where('no_rawat', $_POST['no_rawat'])
       ->toArray();
      $result_ranap = [];
      foreach ($rows_ranap as $row) {
       $row['nomor'] = $i++;
       $row['nama_petugas'] = $this->core->getPegawaiInfo('nama',$row['nip']);
       $row['departemen_petugas'] = $this->core->getDepartemenInfo($this->core->getPegawaiInfo('departemen',$row['nip']));
       $result_ranap[] = $row;
      }

      echo $this->draw('soap.html', ['pemeriksaan' => $result, 'pemeriksaan_ranap' => $result_ranap, 'diagnosa' => $diagnosa, 'prosedur' => $prosedur]);
      exit();
    }

    public function postSaveSOAP()
    {
      $_POST['nip'] = $this->core->getUserInfo('username', null, true);

      if(!$this->db('pemeriksaan_ranap')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->where('nip', $_POST['nip'])->oneArray()) {
        $this->db('pemeriksaan_ranap')->save($_POST);
      } else {
        $this->db('pemeriksaan_ranap')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->where('nip', $_POST['nip'])->save($_POST);
      }
      exit();
    }

    public function postHapusSOAP()
    {
      $this->db('pemeriksaan_ranap')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->delete();
      exit();
    }

    public function anyKontrol()
    {
      $rows = $this->db('booking_registrasi')
        ->select([
          'tanggal_periksa' => 'booking_registrasi.tanggal_periksa',
          'no_reg' => 'booking_registrasi.no_reg',
          'nm_poli' => 'poliklinik.nm_poli',
          'nm_dokter' => 'dokter.nm_dokter',
          'png_jawab' => 'penjab.png_jawab',
          'status' => 'booking_registrasi.status'
        ])
        ->join('poliklinik', 'poliklinik.kd_poli=booking_registrasi.kd_poli')
        ->join('dokter', 'dokter.kd_dokter=booking_registrasi.kd_dokter')
        ->join('penjab', 'penjab.kd_pj=booking_registrasi.kd_pj')
        ->where('no_rkm_medis', $_POST['no_rkm_medis'])
        ->toArray();
      $i = 1;
      $result = [];
      foreach ($rows as $row) {
        $row['nomor'] = $i++;
        $result[] = $row;
      }
      echo $this->draw('kontrol.html', ['booking_registrasi' => $result]);
      exit();
    }

    public function postSaveKontrol()
    {

      $query = $this->db('skdp_bpjs')->save([
        'tahun' => date('Y'),
        'no_rkm_medis' => $_POST['no_rkm_medis'],
        'diagnosa' => $_POST['diagnosa'],
        'terapi' => $_POST['terapi'],
        'alasan1' => $_POST['alasan1'],
        'alasan2' => '',
        'rtl1' => $_POST['rtl1'],
        'rtl2' => '',
        'tanggal_datang' => $_POST['tanggal_datang'],
        'tanggal_rujukan' => $_POST['tanggal_rujukan'],
        'no_antrian' => $this->core->setNoSKDP(),
        'kd_dokter' => $this->core->getUserInfo('username', null, true),
        'status' => 'Menunggu'
      ]);

      if ($query) {
        $this->db('booking_registrasi')
          ->save([
            'tanggal_booking' => date('Y-m-d'),
            'jam_booking' => date('H:i:s'),
            'no_rkm_medis' => $_POST['no_rkm_medis'],
            'tanggal_periksa' => $_POST['tanggal_datang'],
            'kd_dokter' => $this->core->getUserInfo('username', null, true),
            'kd_poli' => $this->core->getRegPeriksaInfo('kd_poli', $_POST['no_rawat']),
            'no_reg' => $this->core->setNoBooking($this->core->getUserInfo('username', null, true), $this->core->getRegPeriksaInfo('kd_poli', $no_rawat), $_POST['tanggal_rujukan']),
            'kd_pj' => $this->core->getRegPeriksaInfo('kd_pj', $_POST['no_rawat']),
            'limit_reg' => 0,
            'waktu_kunjungan' => $_POST['tanggal_datang'].' '.date('H:i:s'),
            'status' => 'Belum'
          ]);
      }

      /*if(!$this->db('pemeriksaan_ranap')->where('no_rawat', $_POST['no_rawat'])->where('tgl_perawatan', $_POST['tgl_perawatan'])->where('jam_rawat', $_POST['jam_rawat'])->oneArray()) {
        $this->db('pemeriksaan_ranap')->save($_POST);
      } else {
        $this->db('pemeriksaan_ranap')->where('no_rawat', $_POST['no_rawat'])->save($_POST);
      }*/
      exit();
    }

    public function postHapusKontrol()
    {
      $this->db('pemeriksaan_ranap')->where('no_rawat', $_POST['no_rawat'])->delete();
      exit();
    }

    public function anyLayanan()
    {
      $layanan = $this->db('jns_perawatan_inap')
        ->where('total_byrdr', '<>', '0')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['layanan'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('layanan.html', ['layanan' => $layanan]);
      exit();
    }

    public function anyObat()
    {
      $obat = $this->db('databarang')
        ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
        ->where('status', '1')
        ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporanap'))
        ->like('databarang.nama_brng', '%'.$_POST['obat'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('obat.html', ['obat' => $obat]);
      exit();
    }

    public function anyObatRacikan()
    {
      $obat = $this->db('databarang')
        ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
        ->where('status', '1')
        ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporanap'))
        ->like('databarang.nama_brng', '%'.$_POST['obat'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('obat.racikan.html', ['obat' => $obat]);
      exit();
    }

    public function anyRacikan()
    {
      $racikan = $this->db('metode_racik')
        ->like('nm_racik', '%'.$_POST['racikan'].'%')
        ->toArray();
      echo $this->draw('racikan.html', ['racikan' => $racikan]);
      exit();
    }

    public function anyLaboratorium()
    {
      $laboratorium = $this->db('jns_perawatan_lab')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['laboratorium'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('laboratorium.html', ['laboratorium' => $laboratorium]);
      exit();
    }

    public function anyRadiologi()
    {
      $radiologi = $this->db('jns_perawatan_radiologi')
        ->where('status', '1')
        ->like('nm_perawatan', '%'.$_POST['radiologi'].'%')
        ->limit(10)
        ->toArray();
      echo $this->draw('radiologi.html', ['radiologi' => $radiologi]);
      exit();
    }

    public function postAturanPakai()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->db('master_aturan_pakai')->like('aturan', $key)->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["aturan"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function postProviderList()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->db('dokter')->like('nm_dokter', $key)->where('status', '1')->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["kd_dokter"].': '.$row["nm_dokter"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function postProviderList2()
    {

      if(isset($_POST["query"])){
        $output = '';
        $key = "%".$_POST["query"]."%";
        $rows = $this->db('petugas')->like('nama', $key)->limit(10)->toArray();
        $output = '';
        if(count($rows)){
          foreach ($rows as $row) {
            $output .= '<li class="list-group-item link-class">'.$row["nip"].': '.$row["nama"].'</li>';
          }
        }
        echo $output;
      }

      exit();

    }

    public function getAjax()
    {
        header('Content-type: text/html');
        $show = isset($_GET['show']) ? $_GET['show'] : "";
        switch($show){
        	default:
          break;
          case "databarang":
          $rows = $this->db('databarang')
            ->join('gudangbarang', 'gudangbarang.kode_brng=databarang.kode_brng')
            ->where('status', '1')
            ->where('stok', '>', '1')
            ->where('gudangbarang.kd_bangsal', $this->settings->get('farmasi.deporanap'))
            ->like('databarang.nama_brng', '%'.$_GET['nama_brng'].'%')
            ->limit(10)
            ->toArray();

          foreach ($rows as $row) {
            $array[] = array(
                'kode_brng' => $row['kode_brng'],
                'nama_brng'  => $row['nama_brng']
            );
          }
          echo json_encode($array, true);
          break;
        }
        exit();
    }

    public function postCekWaktu()
    {
      echo date('H:i:s');
      exit();
    }

    public function getResume($no_rawat)
    {
      $data_resume['pemeriksaan_ranap'] = $this->db('pemeriksaan_ranap')->where('no_rawat', revertNoRawat($no_rawat))->oneArray();
      $data_resume['diagnosa'] = $this->db('diagnosa_pasien')->join('penyakit', 'penyakit.kd_penyakit=diagnosa_pasien.kd_penyakit')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('diagnosa_pasien.status', 'Ralan')->oneArray();
      $data_resume['prosedur'] = $this->db('prosedur_pasien')->join('icd9', 'icd9.kode=prosedur_pasien.kode')->where('no_rawat', revertNoRawat($no_rawat))->where('prioritas', 1)->where('status', 'Ranap')->oneArray();
      echo $this->draw('resume.html', [
        'reg_periksa' => $this->db('reg_periksa')->where('no_rawat', revertNoRawat($no_rawat))->oneArray(),
        'resume_pasien' => $this->db('resume_pasien_ranap')->where('no_rawat', revertNoRawat($no_rawat))->join('dokter', 'dokter.kd_dokter=resume_pasien_ranap.kd_dokter')->toArray(),
        'data_resume' => $data_resume
      ]);
      exit();
    }

    public function getResumeTampil($no_rawat)
    {
      echo $this->draw('resume.tampil.html', ['resume_pasien' => $this->db('resume_pasien_ranap')->where('no_rawat', revertNoRawat($no_rawat))->join('dokter', 'dokter.kd_dokter=resume_pasien_ranap.kd_dokter')->toArray()]);
      exit();
    }

    public function postResumeSave()
    {
      $_POST['kd_dokter']	= $this->core->getUserInfo('username', $_SESSION['mlite_user']);

      if($this->db('resume_pasien_ranap')->where('no_rawat', $_POST['no_rawat'])->where('kd_dokter', $_POST['kd_dokter'])->oneArray()) {
        $this->db('resume_pasien_ranap')
          ->where('no_rawat', $_POST['no_rawat'])
          ->save([
            'kd_dokter'  => $_POST['kd_dokter'],
            'diagnosa_awal' => $_POST['diagnosa_masuk'],
            'alasan' => '-',
            'keluhan_utama' => $_POST['keluhan_utama'],
            'pemeriksaan_fisik' => '-',
            'jalannya_penyakit' => $_POST['riwayat_penyakit'],
            'pemeriksaan_penunjang' => '-',
            'hasil_laborat' => '-',
            'tindakan_dan_operasi' => '-',
            'obat_di_rs' => '-',
            'diagnosa_utama' => $_POST['diagnosa_utama'],
            'kd_diagnosa_utama' => '-',
            'diagnosa_sekunder' => '-',
            'kd_diagnosa_sekunder' => '-',
            'diagnosa_sekunder2' => '-',
            'kd_diagnosa_sekunder2' => '-',
            'diagnosa_sekunder3' => '-',
            'kd_diagnosa_sekunder3' => '-',
            'diagnosa_sekunder4' => '-',
            'kd_diagnosa_sekunder4' => '-',
            'prosedur_utama' => $_POST['prosedur_utama'],
            'kd_prosedur_utama' => '-',
            'prosedur_sekunder' => '-',
            'kd_prosedur_sekunder' => '-',
            'prosedur_sekunder2' => '-',
            'kd_prosedur_sekunder2' => '-',
            'prosedur_sekunder3' => '-',
            'kd_prosedur_sekunder3' => '-',
            'alergi' => '-',
            'diet' => '-',
            'lab_belum' => '-',
            'edukasi' => '-',
            'cara_keluar' => 'Lainnya',
            'ket_keluar' => '-',
            'keadaan'  => $_POST['kondisi_pulang'],
            'ket_keadaan' => '-',
            'dilanjutkan' => 'Lainnya',
            'ket_dilanjutkan' => '-',
            'kontrol' => date('Y-m-d H:i:s'),
            'obat_pulang' => '-'
        ]);
      } else {
        $this->db('resume_pasien_ranap')->save([
          'no_rawat' => $_POST['no_rawat'],
          'kd_dokter'  => $_POST['kd_dokter'],
          'diagnosa_awal' => $_POST['diagnosa_masuk'],
          'alasan' => '-',
          'keluhan_utama' => $_POST['keluhan_utama'],
          'pemeriksaan_fisik' => '-',
          'jalannya_penyakit' => $_POST['riwayat_penyakit'],
          'pemeriksaan_penunjang' => '-',
          'hasil_laborat' => '-',
          'tindakan_dan_operasi' => '-',
          'obat_di_rs' => '-',
          'diagnosa_utama' => $_POST['diagnosa_utama'],
          'kd_diagnosa_utama' => '-',
          'diagnosa_sekunder' => '-',
          'kd_diagnosa_sekunder' => '-',
          'diagnosa_sekunder2' => '-',
          'kd_diagnosa_sekunder2' => '-',
          'diagnosa_sekunder3' => '-',
          'kd_diagnosa_sekunder3' => '-',
          'diagnosa_sekunder4' => '-',
          'kd_diagnosa_sekunder4' => '-',
          'prosedur_utama' => $_POST['prosedur_utama'],
          'kd_prosedur_utama' => '-',
          'prosedur_sekunder' => '-',
          'kd_prosedur_sekunder' => '-',
          'prosedur_sekunder2' => '-',
          'kd_prosedur_sekunder2' => '-',
          'prosedur_sekunder3' => '-',
          'kd_prosedur_sekunder3' => '-',
          'alergi' => '-',
          'diet' => '-',
          'lab_belum' => '-',
          'edukasi' => '-',
          'cara_keluar' => 'Lainnya',
          'ket_keluar' => '-',
          'keadaan'  => $_POST['kondisi_pulang'],
          'ket_keadaan' => '-',
          'dilanjutkan' => 'Lainnya',
          'ket_dilanjutkan' => '-',
          'kontrol' => date('Y-m-d H:i:s'),
          'obat_pulang' => '-'
        ]);
      }
      exit();
    }
    
    public function getJavascript()
    {
        header('Content-type: text/javascript');
        $cek_pegawai = $this->db('pegawai')->where('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']))->oneArray();
        $cek_role = '';
        if($cek_pegawai) {
          $cek_role = $this->core->getPegawaiInfo('nik', $this->core->getUserInfo('username', $_SESSION['mlite_user']));
        }
        echo $this->draw(MODULES.'/dokter_ranap/js/admin/dokter_ranap.js', ['cek_role' => $cek_role]);
        exit();
    }

    private function _addHeaderFiles()
    {
        $this->core->addCSS(url('assets/css/dataTables.bootstrap.min.css'));
        $this->core->addJS(url('assets/jscripts/jquery.dataTables.min.js'));
        $this->core->addJS(url('assets/jscripts/dataTables.bootstrap.min.js'));
        $this->core->addCSS(url('assets/css/bootstrap-datetimepicker.css'));
        $this->core->addJS(url('assets/jscripts/moment-with-locales.js'));
        $this->core->addJS(url('assets/jscripts/bootstrap-datetimepicker.js'));
        $this->core->addJS(url([ADMIN, 'dokter_ranap', 'javascript']), 'footer');
    }

}
