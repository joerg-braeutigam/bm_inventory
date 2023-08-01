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
        $this->version = '1.0.0';
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

        $sql = "SELECT stock.id_product as pr_id, stock.id_product_attribute, stock.quantity as anzahl, 
                pl.`name` as product, cl.`name` as kategorie, pr.price as vk, pr.wholesale_price as ek 
                FROM ps_stock_available stock
                INNER JOIN ps_product pr ON stock.id_product = pr.id_product
                INNER JOIN ps_product_lang pl ON pr.id_product = pl.id_product
                INNER JOIN ps_category_lang cl ON cl.id_category = pr.id_category_default
                WHERE stock.quantity > 0 AND stock.id_product_attribute = 0 AND pr.active = '1' AND cl.id_lang = '" . $id_lang . "'
                ORDER BY cl.`name`";

        $rows = Db::getInstance()->executeS($sql);

        $output = $rows ? $this->generateTable($rows) : $this->displayInfoMessage($sql);

        $baseUrl = Context::getContext()->shop->getBaseURL(true);
        $moduleFolder = $this->_path;
        $downloadUrl = $baseUrl . $moduleFolder . 'download.php';
        $output .= '<form method="post" action="' . $downloadUrl . '" target="_blank">
                    <input type="submit" name="submitExportCsv" value="Export as CSV" class="button" />
                </form>';

        return $output;
    }

    protected function generateTable($rows)
    {
        $output .= '<table border="1">';
        $output .= '<tr><th> &nbsp; Kategorie Name</th><th> &nbsp; Produkt Name</th><th>Produkt Variante</th><th>Anzahl in Stock</th><th>Einkaufspreis netto (Einzel)</th><th>Einkaufspreis netto (Anzahl)</th><th>VK Brutto (Einzel)</th><th>VK Brutto (Gesamt)</th></tr>';

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
                $output .= '<tr><td colspan="2"><strong> &nbsp; '
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
            $sum_vk += $row['vk'] * $row['anzahl'] * 1.19;
            $sum_anz += $row['anzahl'];

            $cat_ek += $row['ek'] * $row['anzahl'];
            $cat_vk += $row['vk'] * $row['anzahl'] * 1.19;
            $cat_anz += $row['anzahl'];

            $context = $this->context;
            $link_tmp = $context->link->getAdminLink('AdminProducts'); //  . '/id_product=' . $product->id; //  . '?#tab-step1';
            $link_array = explode("?", $link_tmp);
            $link = $link_array[0] . "/" . $row['pr_id'] . "?" . $link_array[1] . "#tab-step1";

            // https://bracli.i06.de/meinshop/index.php/sell/catalog/products?_token=XXA8s4C7usvip2iE2TqODSj5cAykERdRqvBzaltZsFM&id_product=454#tab-step1
            // https://bracli.i06.de/meinshop/index.php/sell/catalog/products/8?_token=XXA8s4C7usvip2iE2TqODSj5cAykERdRqvBzaltZsFM#tab-step1

            $output .= '<tr><td> &nbsp; '
                . $row['kategorie'] . '</td><td> &nbsp; '
                . '<a href="' . $link . '" target="_blank">'
                . $row['product'] . ' </a></td><td>'
                . $row['product_variant'] . '</td><td align="right">'
                . $row['anzahl'] . ' &nbsp; </td><td align="right">'
                . number_format($row['ek'], 2) . ' EUR &nbsp; </td><td align="right">'
                . number_format($row['ek'] * $row['anzahl'], 2) . ' EUR &nbsp; </td><td align="right">'
                . number_format($row['vk'] * 1.19, 2) . ' EUR &nbsp; </td><td align="right">'
                . number_format($row['vk'] * $row['anzahl'] * 1.19, 2) . ' EUR &nbsp; </td></tr>';

        }

        // letzte Kategorie
        $output .= '<tr><td colspan="2"><strong> &nbsp; '
            . $currentCategory . ' (am Lager): </strong></td><td></td><td align="right"><strong>'
            . $cat_anz . ' &nbsp; </strong></td><td></td><td align="right"><strong>'
            . number_format($cat_ek, 2) . ' EUR &nbsp; </strong></td><td></td><td align="right"><strong>'
            . number_format($cat_vk, 2) . ' EUR &nbsp; </strong></td></tr>';

        $output .= '<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>';

        $output .= '<tr><td colspan="2"><strong> &nbsp; Gesamt (am Lager): </strong></td><td></td><td align="right"><strong>'
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