/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

package fungsi;

import java.awt.Color;
import java.awt.Component;
import javax.swing.JTable;
import javax.swing.table.DefaultTableCellRenderer;

/**
 *
 * @author Owner
 */
public class WarnaTableKasirRalan extends DefaultTableCellRenderer {
    public Component getTableCellRendererComponent(JTable table, Object value, boolean isSelected, boolean hasFocus, int row, int column){
        Component component = super.getTableCellRendererComponent(table, value, isSelected, hasFocus, row, column);
        if (isSelected){
            component.setFont(component.getFont().deriveFont(java.awt.Font.BOLD)); // teks tebal
        } 
        if (row % 2 == 1){
                component.setBackground(new Color(241, 241, 196));
                component.setForeground(new Color(50,50,50));
            }else{
                component.setBackground(new Color(255,255,255));
                component.setForeground(new Color(50,50,50));
            } 
            if(table.getValueAt(row,10).toString().equals("Sudah")){
                component.setBackground(new Color(242, 169, 169));
                component.setForeground(new Color(30, 27, 27));
            }else if(table.getValueAt(row,10).toString().equals("Batal")){
                component.setBackground(new Color(110, 169, 169));
                component.setForeground(new Color(255, 255, 255));
            }else if(table.getValueAt(row,10).toString().equals("TTV")){
                component.setBackground(new Color(144, 194, 246));
                component.setForeground(new Color(37, 37, 36));
            }else if(table.getValueAt(row,10).toString().equals("Dirujuk")||table.getValueAt(row,10).toString().equals("Meninggal")||table.getValueAt(row,10).toString().equals("Pulang Paksa")){
                component.setBackground(new Color(152,152,156));
                component.setForeground(new Color(245,245,255));
            }else if(table.getValueAt(row,10).toString().equals("Dirawat")){
                component.setBackground(new Color(119,221,119));
                component.setForeground(new Color(245,255,245));
            }
            if(table.getValueAt(row,15).toString().equals("Sudah Bayar")){
                component.setBackground(new Color(50,50,50));
                component.setForeground(new Color(255,255,255));
            }
        return component;
    }

}
