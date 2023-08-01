<?php
// download.php
require('../../config/config.inc.php');
require('../../init.php');

$id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

$sql = "SELECT stock.id_product as pr_id, 
        cl.`name` as kategorie, 
        pl.`name` as product, 
        pr.reference as artnr,
        stock.quantity as anzahl,
        replace(pr.wholesale_price, '.', ',') as ek_e,
        replace(pr.wholesale_price * stock.quantity, '.', ',') as ek_g, 
        replace(pr.price, '.', ',') as vk_e,
        replace(pr.price * stock.quantity, '.', ',') as vk_g
        FROM ps_stock_available stock
        INNER JOIN ps_product pr ON stock.id_product = pr.id_product
        INNER JOIN ps_product_lang pl ON pr.id_product = pl.id_product
        INNER JOIN ps_category_lang cl ON cl.id_category = pr.id_category_default
        WHERE stock.quantity > 0 AND stock.id_product_attribute = 0 AND cl.id_lang = '" . $id_lang . "'
        ORDER BY cl.`name`";

$rows = Db::getInstance()->executeS($sql);

arrayToCsvDownload($rows, "inventory_export.csv", ";");

function arrayToCsvDownload($array, $filename = "export.csv", $delimiter = ";")
{
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '";');

    $f = fopen('php://output', 'w');

    // Write header
    fputcsv($f, array('Artikel ID', 'Kategorie', 'Produkt', 'ArtNr.', 'Anzahl', 'Einkaufspreis (Einzel)', 'Einkaufspreis (Gesamt)', 'VK Brutto (Einzel)', 'VK Brutto (Gesamt)'), $delimiter);

    foreach ($array as $line) {
        fputcsv($f, $line, $delimiter);
    }
}