<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class Bm_Inventory extends Module
{
    public function __construct()
    {
        $this->name = 'bm_inventory';
        $this->tab = 'front_office_features';
        $this->version = '1.1.0';
        $this->author = 'Braeutiigam Media';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BM Inventory');
        $this->description = $this->l('Description of BM Inventory.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/configure.css', 'all');
        }
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('displayBackOfficeHeader');
    }


    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent()
    {
        $id_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $rows = $this->get_products($id_lang);

        $new = [];
        foreach ($rows as $row) {
            if ($row['type'] == "combinations") {
                $new = array_merge($new, $this->get_product_combination($row, $id_lang));
            } else {
                $new[] = $row;
            }
        }


        // $output .= "<pre>" . print_r($new, 1) . "</pre>";
        // $output = "<pre>" . print_r($rows, 1) . "</pre>";

        $output = $new ? $this->generateTable($new) : $this->displayInfoMessage("Fehler!");

        $baseUrl = Context::getContext()->shop->getBaseURL(true);
        $moduleFolder = $this->_path;
        $downloadUrl = $baseUrl . $moduleFolder . 'download.php';
        $output .= '<form method="post" action="' . $downloadUrl . '" target="_blank">
                    <input type="submit" name="submitExportCsv" value="Export as CSV" class="button" />
                </form>';

        return $output;
    }

    protected function get_product_combination($row, $id_lang)
    {
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
        for ($i = 0; $i < count($add); $i++) {
            if ($add[$i]['quantity'] > 0) {
                if ($id_product_attribute != $add[$i]['id_product_attribute']) { // neue Variante
                    $id_product_attribute = $add[$i]['id_product_attribute'];

                    $new[$id_product_attribute] = array_merge($add[$i], $row);
                    $new[$id_product_attribute]['anzahl'] = $add[$i]['quantity'];

                    // variante
                    $new[$id_product_attribute]['variante'] = $add[$i]['wert'] . ": " . $add[$i]['inhalt'];
                    unset($new[$id_product_attribute]['wert']);
                    unset($new[$id_product_attribute]['inhalt']);
                } else {

                    // variante
                    $new[$id_product_attribute]['variante'] .= " / " . $add[$i]['wert'] . ": " . $add[$i]['inhalt'];
                    unset($new[$id_product_attribute]['wert']);
                    unset($new[$id_product_attribute]['inhalt']);
                }
                unset($new[$id_product_attribute]['quantity']);
            }
        }

        return $new;
    }

    protected function get_products($id_lang)
    {
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

        // Brand holen 
        for ($i = 0; $i < count($rows); $i++) {
            $sql_brand = "select m.id_manufacturer, m.`name` from ps_manufacturer m, ps_product pr
                    where pr.id_manufacturer = m.id_manufacturer
                    and pr.id_product = '" . $rows[$i]['id'] . "'";
            $query_brand = Db::getInstance()->executeS($sql_brand);
            $rows[$i]['brand'] = $query_brand[0]['name'];
        }

        return $rows;
    }

    protected function generateTable($rows)
    {
        $output .= '<table border="1" valign="left" align="top">';
        $output .= '<tr><th> &nbsp; Kategorie Name &nbsp; </th>
                        <th> &nbsp; Marke &nbsp; </th>
                        <th> &nbsp; EAN &nbsp; </th>
                        <th> &nbsp; Produkt Name &nbsp; </th>
                        <th> &nbsp; Größe / Farbe &nbsp; </th>
                        <th> &nbsp; Anzahl im Lager &nbsp; </th>
                        <th> &nbsp; EK netto (Einzel) &nbsp; </th>
                        <th> &nbsp; EK netto (Anzahl) &nbsp; </th>
                        <th> &nbsp; VK Brutto (Einzel) &nbsp; </th>
                        <th> &nbsp; VK Brutto (Gesamt) &nbsp; </th>
                    </tr>';

        $currentCategory = '';
        $sum_ek = 0;
        $sum_vk = 0;
        $sum_anz = 0;
        $cat_ek = 0;
        $cat_vk = 0;
        $cat_anz = 0;
        foreach ($rows as $row) {
            if (($currentCategory != $row['kategorie']) && $currentCategory != '') {
                // summary of category 
                $output .= '<tr><td colspan="4"><strong> &nbsp; '
                    . $currentCategory . ' (am Lager): </strong></td><td></td><td align="right"><strong>'
                    . $cat_anz . ' &nbsp; </strong></td><td></td><td align="right"><strong>'
                    . number_format($cat_ek, 2) . ' EUR &nbsp; </strong></td><td></td><td align="right"><strong>'
                    . number_format($cat_vk, 2) . ' EUR &nbsp; </strong></td></tr>';

                $output .= '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';

                $cat_ek = 0;
                $cat_vk = 0;
                $cat_anz = 0;
            }


            $currentCategory = $row['kategorie'];
            $sum_ek += $row['ek'] * $row['anzahl'];
            $sum_vk += $row['vk'] * $row['anzahl'];
            $sum_anz += $row['anzahl'];

            $cat_ek += $row['ek'] * $row['anzahl'];
            $cat_vk += $row['vk'] * $row['anzahl'];
            $cat_anz += $row['anzahl'];

            $context = $this->context;
            $link_tmp = $context->link->getAdminLink('AdminProducts'); //  . '/id_product=' . $product->id; //  . '?#tab-step1';
            $link_array = explode("?", $link_tmp);
            $link = $link_array[0] . "/" . $row['id'] . "?" . $link_array[1] . "#tab-step1";

            $output .= '<tr> '
                . ' <td> &nbsp; ' . $row['kategorie'] . '</td>'
                . ' <td> &nbsp; ' . $row['brand'] . '<br></td>'
                . ' <td> &nbsp; ' . $row['ean'] . '<br></td>'
                . ' <td> &nbsp; <a href="' . $link . '" target="_blank">' . $row['product'] . '  &nbsp; </a></td> '
                . ' <td> &nbsp; ' . $row['variante'] . ' &nbsp; </td> '
                . ' <td align="right">' . $row['anzahl'] . ' &nbsp; </td> '
                . ' <td align="right">' . number_format($row['ek'], 2) . ' EUR &nbsp; </td> '
                . ' <td align="right">' . number_format($row['ek'] * $row['anzahl'], 2) . ' EUR &nbsp; </td> '
                . ' <td align="right">' . number_format($row['vk'], 2) . ' EUR &nbsp; </td> '
                . ' <td align="right">' . number_format($row['vk'] * $row['anzahl'], 2) . ' EUR &nbsp; </td>
                </tr>';
        }

        // letzte Kategorie
        $output .= '<tr><td colspan="4"><strong> &nbsp; '
            . $currentCategory . ' (am Lager): </strong></td><td></td><td align="right"><strong>'
            . $cat_anz . ' &nbsp; </strong></td><td></td><td align="right"><strong>'
            . number_format($cat_ek, 2) . ' EUR &nbsp; </strong></td><td></td><td align="right"><strong>'
            . number_format($cat_vk, 2) . ' EUR &nbsp; </strong></td></tr>';

        $output .= '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';

        $output .= '<tr><td colspan="4"><strong> &nbsp; Gesamt (am Lager): </strong></td><td></td><td align="right"><strong>'
            . $sum_anz . ' &nbsp; </strong></td><td></td><td align="right"><strong>'
            . number_format($sum_ek, 2) . ' EUR &nbsp; </strong></td><td></td><td align="right"><strong>'
            . number_format($sum_vk, 2) . ' EUR &nbsp; </strong></td></tr>';

        $output .= '</table>';

        return $output;
    }

    protected function displayInfoMessage($message)
    {
        return '<div class="alert alert-info">' . $this->l($message) . '</div>';
    }

}