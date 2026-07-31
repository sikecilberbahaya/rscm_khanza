package rekammedis;

import fungsi.koneksiDB;
import java.awt.Font;
import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import javax.swing.ImageIcon;
import javax.swing.JDialog;
import javax.swing.JOptionPane;
import org.jfree.chart.ChartPanel;
import org.jfree.chart.JFreeChart;
import org.jfree.chart.axis.CategoryAxis;
import org.jfree.chart.axis.NumberAxis;
import org.jfree.chart.plot.CategoryPlot;
import org.jfree.chart.renderer.category.LineAndShapeRenderer;
import org.jfree.data.category.DefaultCategoryDataset;

public class DlgGrafikObservasiRanap extends JDialog {

    public DlgGrafikObservasiRanap(String judul, String item, String noRawat) {
        setTitle(judul);
        JFreeChart chart;
        if (item.equals("SEMUA")) {
            DefaultCategoryDataset[] d = buildSemuaDataset(noRawat);
            if (d[0].getColumnCount() == 0 && d[1].getColumnCount() == 0) {
                JOptionPane.showMessageDialog(null, "Belum ada data observasi untuk pasien ini.");
                dispose();
                return;
            }
            chart = buatChartSemua(d[0], d[1], judul);
        } else {
            DefaultCategoryDataset ds = buildDataset(item, noRawat);
            if (ds.getColumnCount() == 0) {
                JOptionPane.showMessageDialog(null, "Belum ada data observasi untuk pasien ini.");
                dispose();
                return;
            }
            chart = new JFreeChart(
                    item,
                    new Font("SansSerif", Font.BOLD, 14),
                    new CategoryPlot(ds, new CategoryAxis("Waktu"),
                            new NumberAxis(item), new LineAndShapeRenderer()),
                    true);
        }
        ChartPanel cp = new ChartPanel(chart);
        cp.setPreferredSize(new java.awt.Dimension(800, 500));
        setContentPane(cp);
        setIconImage(new ImageIcon(getClass().getResource("/picture/addressbook-edit24.png")).getImage());
        setSize(800, 500);
        setLocationRelativeTo(null);
        setDefaultCloseOperation(DISPOSE_ON_CLOSE);
    }

    private static JFreeChart buatChartSemua(DefaultCategoryDataset kiri, DefaultCategoryDataset kanan, String judul) {
        LineAndShapeRenderer rKiri = new LineAndShapeRenderer();
        rKiri.setSeriesPaint(0, new java.awt.Color(220, 50, 50));
        rKiri.setSeriesPaint(1, new java.awt.Color(60, 60, 200));
        rKiri.setSeriesPaint(2, new java.awt.Color(50, 160, 50));
        rKiri.setSeriesPaint(3, new java.awt.Color(230, 150, 30));
        LineAndShapeRenderer rKanan = new LineAndShapeRenderer();
        rKanan.setSeriesPaint(0, new java.awt.Color(180, 60, 180));
        rKanan.setSeriesPaint(1, new java.awt.Color(0, 170, 170));

        CategoryAxis domain = new CategoryAxis("Waktu");
        org.jfree.chart.plot.CombinedDomainCategoryPlot combined = new org.jfree.chart.plot.CombinedDomainCategoryPlot(domain);
        combined.add(new CategoryPlot(kiri, null, new NumberAxis("TD/HR/RR"), rKiri), 2);
        combined.add(new CategoryPlot(kanan, null, new NumberAxis("Suhu/SpO2"), rKanan), 1);
        return new JFreeChart(judul, new Font("SansSerif", Font.BOLD, 14), combined, true);
    }

    public static DefaultCategoryDataset[] buildSemuaDataset(String noRawat) {
        DefaultCategoryDataset kiri = new DefaultCategoryDataset();
        DefaultCategoryDataset kanan = new DefaultCategoryDataset();
        Connection con = koneksiDB.condb();
        PreparedStatement ps = null;
        ResultSet rs = null;
        try {
            ps = con.prepareStatement(
                    "select tgl_perawatan, jam_rawat, td, hr, rr, suhu, spo2 " +
                    "from catatan_observasi_ranap where no_rawat=? " +
                    "order by tgl_perawatan, jam_rawat");
            ps.setString(1, noRawat);
            rs = ps.executeQuery();
            while (rs.next()) {
                String waktu = formatWaktu(rs.getString("tgl_perawatan"), rs.getString("jam_rawat"));
                tambahItem(kiri, "TD", waktu, rs);
                tambahItem(kiri, "HR", waktu, rs);
                tambahItem(kiri, "RR", waktu, rs);
                tambahItem(kanan, "Suhu", waktu, rs);
                tambahItem(kanan, "SpO2", waktu, rs);
            }
        } catch (Exception e) {
            System.out.println("Notifikasi : " + e);
        } finally {
            if (rs != null) { try { rs.close(); } catch (Exception e) {} }
            if (ps != null) { try { ps.close(); } catch (Exception e) {} }
        }
        return new DefaultCategoryDataset[]{kiri, kanan};
    }

    public static DefaultCategoryDataset buildDataset(String item, String noRawat) {
        DefaultCategoryDataset result = new DefaultCategoryDataset();
        Connection con = koneksiDB.condb();
        PreparedStatement ps = null;
        ResultSet rs = null;
        try {
            ps = con.prepareStatement(
                    "select tgl_perawatan, jam_rawat, td, hr, rr, suhu, spo2 " +
                    "from catatan_observasi_ranap where no_rawat=? " +
                    "order by tgl_perawatan, jam_rawat");
            ps.setString(1, noRawat);
            rs = ps.executeQuery();
            while (rs.next()) {
                String waktu = formatWaktu(rs.getString("tgl_perawatan"), rs.getString("jam_rawat"));
                if (item.equals("SEMUA")) {
                    tambahItem(result, "TD", waktu, rs);
                    tambahItem(result, "HR", waktu, rs);
                    tambahItem(result, "RR", waktu, rs);
                    tambahItem(result, "Suhu", waktu, rs);
                    tambahItem(result, "SpO2", waktu, rs);
                } else {
                    tambahItem(result, item, waktu, rs);
                }
            }
        } catch (Exception e) {
            System.out.println("Notifikasi : " + e);
        } finally {
            if (rs != null) { try { rs.close(); } catch (Exception e) {} }
            if (ps != null) { try { ps.close(); } catch (Exception e) {} }
        }
        return result;
    }

    private static void tambahItem(DefaultCategoryDataset ds, String item, String waktu, ResultSet rs) throws java.sql.SQLException {
        if (item.equals("TD")) {
            String td = rs.getString("td");
            String[] parts = td == null ? new String[0] : td.split("/");
            if (parts.length >= 1) {
                addIfNumeric(ds, "Sistolik", waktu, parts[0]);
                if (parts.length >= 2) {
                    addIfNumeric(ds, "Diastolik", waktu, parts[1]);
                }
            }
        } else if (item.equals("HR")) {
            addIfNumeric(ds, "HR", waktu, rs.getString("hr"));
        } else if (item.equals("RR")) {
            addIfNumeric(ds, "RR", waktu, rs.getString("rr"));
        } else if (item.equals("Suhu")) {
            addIfNumeric(ds, "Suhu", waktu, rs.getString("suhu"));
        } else if (item.equals("SpO2")) {
            addIfNumeric(ds, "SpO2", waktu, rs.getString("spo2"));
        }
    }

    private static void addIfNumeric(DefaultCategoryDataset ds, String series, String waktu, String val) {
        if (val == null || val.trim().isEmpty()) return;
        try {
            ds.addValue(Double.parseDouble(val.trim().replace(",", ".")), series, waktu);
        } catch (NumberFormatException e) {
            // lewati nilai tidak valid
        }
    }

    private static String formatWaktu(String tgl, String jam) {
        String j = jam == null ? "" : jam.length() >= 8 ? jam.substring(0, 8) : jam;
        if (tgl != null && tgl.length() >= 10) {
            return tgl.substring(8, 10) + "-" + tgl.substring(5, 7) + " " + j;
        }
        return (tgl == null ? "" : tgl) + " " + j;
    }

    public static void main(String[] args) {
        DefaultCategoryDataset d = new DefaultCategoryDataset();
        addIfNumeric(d, "Sistolik", "31-07 14:30", "120");
        addIfNumeric(d, "Diastolik", "31-07 14:30", "80");
        addIfNumeric(d, "Sistolik", "31-07 16:00", "");
        addIfNumeric(d, "Sistolik", "31-07 16:00", "abc");
        addIfNumeric(d, "Sistolik", "31-07 16:00", "36,7");
        cek(d.getColumnCount() == 2, "hanya 2 waktu valid");
        cek(d.getRowCount() == 2, "2 series (Sistolik, Diastolik)");
        cek(formatWaktu("2026-07-31", "14:30:05").equals("31-07 14:30:05"), "format waktu salah");
        cek(tdSistolik("89").equals("89"), "TD tunggal harus jadi Sistolik");
        cek(tdSistolik("120/80").equals("120"), "TD 120/80: sistolik=120");
        cek(tdDiastolik("120/80").equals("80"), "TD 120/80: diastolik=80");
        System.out.println("OK: dataset dan parse valid.");
    }

    private static String tdSistolik(String td) {
        String[] parts = td.split("/");
        return parts.length >= 1 ? parts[0] : "";
    }

    private static String tdDiastolik(String td) {
        String[] parts = td.split("/");
        return parts.length >= 2 ? parts[1] : "";
    }

    private static void cek(boolean ok, String msg) {
        if (!ok) throw new AssertionError(msg);
    }
}
