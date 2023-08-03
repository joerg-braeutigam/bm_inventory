<?php
// download.php
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: /");
    exit;
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=inventar-shop.csv');
require('../../config/config.inc.php');
require('../../init.php');


$output = fopen('php://output', 'w');

$id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

fputcsv($output, array('Kategorie Name', 'Marke', 'EAN', 'ID', 'Produkt Name', 'Größe / Farbe', 'Anzahl im Lager', 'EK netto (Einzel)', 'EK netto (Anzahl)', 'VK Brutto (Einzel)', 'VK Brutto (Gesamt)'));

$sql = "SELECT 
            cl.`name` as kategorie, 
            stock.id_product as id, 
            pl.`name` as product, 
            stock.quantity as anzahl, 
            round(ph.wholesale_price, 2) as ek, 
            round(ph.price * 1.19, 2) as vk, 
            pr.product_type as type


            FROM ps_stock_available stock
                    INNER JOIN ps_product_shop ph ON stock.id_product = ph.id_product
                    INNER JOIN ps_product as pr ON stock.id_product = pr.id_product
                    INNER JOIN ps_product_lang pl ON pr.id_product = pl.id_product
                    INNER JOIN ps_category_lang cl ON cl.id_category = pr.id_category_default
            WHERE 
                stock.quantity > 0 AND 
                stock.id_product_attribute = 0 AND 
                pr.active = '1' AND 
                pl.id_lang = '$id_lang' AND
                cl.id_lang = '$id_lang'
            ORDER BY cl.`name`";

$rows = Db::getInstance()->executeS($sql);

foreach ($rows as $row) {
    // Brand holen 
    $sql_brand = "select m.id_manufacturer, m.`name` from ps_manufacturer m, ps_product pr
                    where pr.id_manufacturer = m.id_manufacturer
                    and pr.id_product = '" . $row['id'] . "'";
    $query_brand = Db::getInstance()->executeS($sql_brand);
    $row['brand'] = $query_brand[0]['name'];

    if ($row['type'] == "combinations") {
        $data = array(
            $row['kategorie'],
            $row['brand'],
            $row['ean'],
            $row['id'],
            $row['product'],
            $row['variante'],
            $row['anzahl'],
            $row['ek'],
            $row['ek'] * $row['anzahl'],
            $row['vk'],
            $row['vk'] * $row['anzahl']
        );

        // Varianten holen 
        $sql_add = "select 
                stock.id_product, 
                stock.quantity, 
                stock.id_product_attribute, 
                attr.ean13 as ean,
                group_lang.name as wert,
                attr_lang.name as inhalt
            from 
                ps_stock_available stock, 
                ps_product_attribute attr, 
                ps_product_attribute_combination combi,
                ps_attribute attribute,
                ps_attribute_lang as attr_lang,
                ps_attribute_group_lang group_lang
            where 
                stock.id_product = '" . $row['id'] . "' and 
                stock.id_product_attribute != '0' and 
                stock.id_product_attribute = attr.id_product_attribute and 
                attr.id_product_attribute = combi.id_product_attribute and 
                combi.id_attribute = attribute.id_attribute and 
                attribute.id_attribute_group = group_lang.id_attribute_group and 
                attr_lang.id_attribute = attribute.id_attribute and 
                group_lang.id_lang = '" . $id_lang . "' and 
                attr_lang.id_lang = '" . $id_lang . "'";
        $add = Db::getInstance()->executeS($sql_add);

        $new = [];
        $id_product_attribute = 0;

        $var_product = [];
        for ($i = 0; $i < count($add); $i++) {
            if ($add[$i]['quantity'] > 0) {
                if ($id_product_attribute != $add[$i]['id_product_attribute']) { // neue Variante
                    $id_product_attribute = $add[$i]['id_product_attribute'];

                    $var_product[$id_product_attribute]['anzahl'] = $add[$i]['quantity'];
                    $var_product[$id_product_attribute]['ean'] = $add[$i]['ean'];
                    // variante
                    $var_product[$id_product_attribute]['variante'] = $add[$i]['wert'] . ": " . $add[$i]['inhalt'];
                } else {

                    // variante
                    $var_product[$id_product_attribute]['variante'] .= " / " . $add[$i]['wert'] . ": " . $add[$i]['inhalt'];
                }
            }
        }
        foreach ($var_product as $product) {
            $data[2] = $product['ean'];
            $data[5] = $product['variante'];
            $data[6] = $product['anzahl'];
            $data[8] = $row['ek'] * $product['anzahl'];
            $data[10] = $row['vk'] * $product['anzahl'];
            fputcsv($output, $data);
            // print_r($data);
        }
    } else {
        $data = array(
            $row['kategorie'],
            $row['brand'],
            $row['ean'],
            $row['id'],
            $row['product'],
            $row['variante'],
            $row['anzahl'],
            $row['ek'],
            $row['ek'] * $row['anzahl'],
            $row['vk'],
            $row['vk'] * $row['anzahl']
        );

        fputcsv($output, $data);
        // print_r($data);
    }
}

fclose($output);