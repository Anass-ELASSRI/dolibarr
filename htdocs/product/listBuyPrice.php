<?php

require_once '../vendor/autoload.php';
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';




// if (!$user->rights->salaries->read) {
// 	accessforbidden("you don't have right for this page");
// }



$action = GETPOST('action', 'alpha');
$id = GETPOST('id', 'alpha');
$commands = array();
$sql = "SELECT s.nom ,c.date_commande, TRUNCATE(cf.subprice, 2) as prix_vente, cf.qty as quantity, TRUNCATE(cf.total_ht, 2) as total_ht, TRUNCATE(cf.total_ttc, 2) as total_ttc FROM  llx_commande_fournisseurdet as cf, llx_commande_fournisseur as c, llx_societe as s WHERE s.rowid = c.fk_soc and c.rowid = cf.fk_commande and fk_product = $id and product_type = 0 and c.fk_statut in (4,5) ORDER BY c.date_commande DESC, cf.rowid DESC";
$res = $db->query($sql);
if ($res->num_rows) {
    while ($row = $res->fetch_assoc()) {
        array_push($commands, [$row['nom'], $row['date_commande'], $row['prix_vente'], $row['quantity'], $row['total_ht'], $row['total_ttc']]);
    }
}


$text = "List Des Prix De D'achat";

llxHeader("", "$text");

print_barre_liste($text, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'setup', 0, $morehtmlright.' '.$newcardbutton, '', $limit, 0, 0, 1);

print "
<style>
    section.tab-con  {
        background: -webkit-linear-gradient(left, #25c481, #25b7c4);
        background: linear-gradient(to right, #25c481, #25b7c4);
        font-family: 'Roboto', sans-serif;
    }
    section.tab-con th:nth-child(even),section.tab-con td:nth-child(even) {
      background: rgba(0, 0, 0, .08);
 }
  section.tab-con tbody tr:nth-child(odd) {
      background: rgba(0, 0, 0, .08);
 }
    h1{
    font-size: 30px;
    color: #fff;
    text-transform: uppercase;
    font-weight: 300;
    text-align: center;
    margin-bottom: 15px;
    }
    table{
    width:100%;
    table-layout: fixed;
    }
    .tbl-header{
    background-color: rgba(255,255,255,0.3);
    }
    .tbl-content{
      max-height:715px;

    overflow-x:auto;
    margin-top: 0px;
    border: 1px solid rgba(255,255,255,0.3);
    }
    th{
    padding: 20px 15px;
    text-align: left;
    font-weight: 600;
     font-size: 14px;
    color: #fff;
    text-transform: uppercase;
    }
    td{
    padding: 15px;
    text-align: left;
    vertical-align:middle;
    font-weight: 500;
     font-size: 14px;
    color: #fff;
    border-bottom: solid 1px rgba(255,255,255,0.1);
    }



    @import url(https://fonts.googleapis.com/css?family=Roboto:400,500,300,700);


    /* follow me template */
    .made-with-love {
    margin-top: 40px;
    padding: 10px;
    clear: left;
    text-align: center;
    font-size: 10px;
    font-family: arial;
    color: #fff;
    }
    .made-with-love i {
    font-style: normal;
    color: #F50057;
    font-size: 14px;
    position: relative;
    top: 2px;
    }
    .made-with-love a {
    color: #fff;
    text-decoration: none;
    }
    .made-with-love a:hover {
    text-decoration: underline;
    }


    /* for custom scrollbar for webkit browser*/

    ::-webkit-scrollbar {
        width: 6px;
    } 
    ::-webkit-scrollbar-track {
        -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,0.3); 
    } 
    ::-webkit-scrollbar-thumb {
        -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,0.3); 
    }

</style>";
print '
<section class="tab-con">
  <!--for demo wrap-->
  <h1>List Des Prix De D\'achat</h1>
  <div class="tbl-header">
    <table cellpadding="0" cellspacing="0" border="0">
      <thead>
        <tr>
          <th>Nom Societe</th>
          <th>Date commande</th>
          <th>prix d\'achat</th>
          <th>quantité</th>
          <th>total ht</th>
          <th>total ttc </th>
        </tr>
      </thead>
    </table>
  </div>
  <div class="tbl-content">
    <table cellpadding="0" cellspacing="0" border="0">
    <tbody>
    ';
    foreach ($commands as $comm) {
        print "
        <tr>
          <th>$comm[0]</th>
          <th>$comm[1]</th>
          <th>$comm[2]</th>
          <th>$comm[3] %</th>
          <th>$comm[4]</th>
          <th>$comm[5]</th>
        </tr>
        ";
    }
    print '
    </tbody>
    </table>
  </div>
</section>';


print "
  <script>
    $(window).on('load resize ', function() {
      var scrollWidth = $('.tbl-content').width() - $('.tbl-content table').width();
      $('.tbl-header').css({'padding-right':scrollWidth});
    }).resize();
  </script>";


?>