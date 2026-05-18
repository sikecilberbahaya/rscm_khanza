package fungsi;

import java.awt.Color;
import java.awt.Component;
import javax.swing.JTable;
import javax.swing.table.DefaultTableCellRenderer;

public class WarnaTableResep extends DefaultTableCellRenderer {
    public int kolom;

    @Override
    public Component getTableCellRendererComponent(JTable table, Object value, boolean isSelected, boolean hasFocus, int row, int column) {
        Component component = super.getTableCellRendererComponent(table, value, isSelected, hasFocus, row, column);

        // Jika baris sedang dipilih, ubah warna background dan foreground
        if (isSelected) {
            component.setFont(component.getFont().deriveFont(java.awt.Font.BOLD)); // teks tebal
        } else {
            // Warna latar belakang baris ganjil/genap
            if (row % 2 == 1) {
                component.setBackground(new Color(246, 246, 218));
            } else {
                component.setBackground(Color.WHITE);
            }

            // Pewarnaan khusus untuk kolom tertentu
            if (column == kolom) {
                component.setBackground(new Color(215, 215, 255));
                component.setForeground(Color.WHITE);
                try {
                    if (!table.getValueAt(row, kolom).toString().equals("")) {
                        component.setBackground(Color.WHITE);
                        component.setForeground(new Color(1, 19, 19));
                    }
                } catch (Exception e) {
                    // Kosongkan atau log jika perlu
                }
            } else {
                component.setForeground(new Color(70, 70, 70));
            }
        }

        return component;
    }
}
