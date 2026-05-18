package fungsi;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.text.ParseException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.concurrent.TimeUnit;

/**
 * Helper class untuk berbagai fungsi utilitas aplikasi SIMRS
 * @author SIMRS Khanza
 */
public class MagicHelper {
    
    private static Connection koneksi = koneksiDB.condb();
    private static PreparedStatement ps;
    private static ResultSet rs;
    
    /**
     * Mengecek apakah pasien dapat melakukan kunjungan berdasarkan tanggal terakhir kunjungan
     * Jika selisih hari >= 7, maka pasien dapat melakukan kunjungan tanpa notifikasi
     * 
     * @param tglTerakhirKunjungan Tanggal terakhir kunjungan dalam format yyyy-MM-dd
     * @return true jika sudah lebih dari atau sama dengan 7 hari, false jika kurang dari 7 hari
     */
    public static boolean canVisitFromLastVisit(String tglTerakhirKunjungan) {
        int day = 7; // Jumlah hari muncul notifikasi pengingat -> 7 = Rentang Waktu 7 Hari

        try {
            // Parse tanggal terakhir kunjungan
            SimpleDateFormat sdf = new SimpleDateFormat("yyyy-MM-dd");
            Date lastVisitDate = sdf.parse(tglTerakhirKunjungan);

            // Dapatkan tanggal hari ini
            Date today = new Date();

            // Hitung selisih hari
            long diffInMillis = today.getTime() - lastVisitDate.getTime();
            long diffInDays = TimeUnit.MILLISECONDS.toDays(diffInMillis);

            System.out.println("Tgl Terakhir: " + tglTerakhirKunjungan);
            System.out.println("Selisih: " + diffInDays);
            return diffInDays >= day;
        } catch (ParseException e) {
            e.printStackTrace();
            return false;
        }
    }
    
    /**
     * Mengambil data kunjungan terakhir pasien berdasarkan no RM dan status bayar
     * 
     * @param noRm Nomor Rekam Medis pasien
     * @param statusBayar Kode cara bayar (kd_pj)
     * @return List berisi [0]=no_rawat, [1]=tgl_registrasi, [2]=nm_poli, [3]=nm_dokter, [4]=nm_pasien
     *         atau List kosong jika tidak ada kunjungan sebelumnya
     */
    public static List<String> lastVisit(String noRm, String statusBayar) {
        List<String> data = new ArrayList<>();
        try {
            ps = koneksi.prepareStatement(
                "SELECT rp.no_rawat, rp.tgl_registrasi, p.nm_poli, d.nm_dokter, ps.nm_pasien " +
                "FROM reg_periksa rp " +
                "INNER JOIN poliklinik p ON rp.kd_poli = p.kd_poli " +
                "INNER JOIN dokter d ON rp.kd_dokter = d.kd_dokter " +
                "INNER JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis " +
                "WHERE rp.no_rkm_medis = ? AND rp.kd_pj = ? AND rp.stts <> 'Batal' " +
                "ORDER BY rp.tgl_registrasi DESC LIMIT 1"
            );
            try {
                ps.setString(1, noRm);
                ps.setString(2, statusBayar);
                rs = ps.executeQuery();
                while (rs.next()) {
                    data.add(0, rs.getString("no_rawat"));
                    data.add(1, rs.getString("tgl_registrasi"));
                    data.add(2, rs.getString("nm_poli"));
                    data.add(3, rs.getString("nm_dokter"));
                    data.add(4, rs.getString("nm_pasien"));
                }
            } catch (Exception e) {
                System.out.println("Notif lastVisit: " + e);
            } finally {
                if (rs != null) {
                    rs.close();
                }
                if (ps != null) {
                    ps.close();
                }
            }
        } catch (Exception e) {
            System.out.println("Notif lastVisit: " + e);
        }
        
        return data;
    }
}
