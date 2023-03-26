<?php

$i = 0;
$result = $db->query($sql0);
if ($result) {
    $nbtotalofrecords = $db->num_rows($result);
}

$result = $db->query($sql0);
if (!$result) {
    dol_print_error($db);
    exit;
}

$num = $db->num_rows($result);

// if ($num == 1 && !empty($conf->global->MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE) && $sall) {
//     $obj = $db->fetch_object($resql);
//     $id = $obj->rowid;
//     header("Location: " . DOL_URL_ROOT . '/user/card.php?id=' . $id);
//     exit;
// }
$rubBases = array();
$salaireMonsuelTot = 0;
$salaireHoraireTot = 0;
$primeDancienTot = 0;
$soldeCongeTot = 0;
$brutGlobalTot = 0;
$enBruts = array();
$cotisations = array();
$pasEnBruts = array();
$workingdaysdeclaréTot = 0;
$netImposableTot = 0;
$chargeFamilleTot = 0;
$irNetTot  = 0;
$irbase = 0;
$prev_arrondiTot = 0;
$arrondiTot  = 0;
$totalNetTot = 0;
$brutImposableTot = 0;

$debitTot = 0;
$creditTot = 0;
$baseTot = 0;

$sql = "SELECT * FROM llx_Paie_Rub";
$res = $db->query($sql);
while ($row = $res->fetch_assoc()) {
    $Rubs[$row['rub']] = $row;
}

while ($i < $num) {
    $Taux = 0;
    $workingdaysdeclaré = 0;
    $congeDays = 0;
    $bases["salaire mensuel"] = 0;

    $obj = $db->fetch_object($result);
    //$sql2="SELECT ex.name FROM " . MAIN_DB_PREFIX . "extrafields as ex LEFT JOIN " . MAIN_DB_PREFIX . "user_extrafields as eu on ex.rowid=eu.fk"

    $userstatic->id = $obj->rowid;
    $userstatic->admin = $obj->admin;
    $userstatic->ref = $obj->label;
    $userstatic->login = $obj->login;
    $userstatic->statut = $obj->statut;
    $userstatic->email = $obj->email;
    $userstatic->gender = $obj->gender;
    $userstatic->socid = $obj->fk_soc;
    $userstatic->firstname = $obj->firstname;
    $userstatic->lastname = $obj->lastname;
    $userstatic->employee = $obj->employee;
    $userstatic->photo = $obj->photo;

    $object->fetch($obj->rowid);


    $sql1 = "SELECT s.fk_user FROM llx_payment_salary as s WHERE s.fk_user=" . $obj->rowid . " AND year(datep)=" . $year . " AND month(datep)=" . $month;
    $res1 = $db->query($sql1);
    if ((strtotime($obj->dateemploymentend) < strtotime('23' . '-' . $prev_month . '-' . $prev_year) && $obj->dateemploymentend != '') || $obj->dateemployment == '' || (strtotime($obj->dateemployment) > strtotime('22-' . $month . '-' . $year) && $obj->dateemployment != '') || $obj->statut == 0) {
        $i++;
        continue;
    }

    /*************************************** ******************************************
     * ********************************** 
     * Get salary informations of all emploiyes
     */

    //Get Parameters from database
    $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "Paie_bdpParameters";
    $res = $db->query($sql);
    $params = ((object)($res))->fetch_assoc();

    //Get user extra informations from database
    $sql = "SELECT situation, enfants, idpointage FROM " . MAIN_DB_PREFIX . $object->table_element . "_extrafields WHERE fk_object=" . $object->id;
    $res = $db->query($sql);
    if ($res->num_rows > 0)
        $extrafields = ((object)($res))->fetch_assoc();

    $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "Paie_MonthDeclaration m, " . MAIN_DB_PREFIX . "Paie_MonthDeclarationRubs r WHERE r.userid=m.userid AND r.month=m.month AND r.year = m.year AND m.userid=$object->id AND m.month=$month AND m.year = $year";
    $res = $db->query($sql);
    if (((object)$res)->num_rows > 0) {
        $row = $res->fetch_assoc();

        $workingdaysdeclaré = (int)$row["workingDays"];
        $workingdaysdeclaréTot += $workingdaysdeclaré;
        $netImposable = (float)$row["netImposable"];
        $netImposableTot += $netImposable;
        $brutImposable = (float)$row["salaireBrut"];
        $brutImposableTot += $brutImposable;
        $irNet = (float)$row["ir"];
        if ($irNet > 0) {
            $irNetTot += $irNet;
            $irbase += $netImposable;
        }
        $totalNet = (float)$row["salaireNet"];
        $totalNetTot += $totalNet;

        $situation = $row["situationFamiliale"];
        $type = $row["type"];
        $role = $row["post"];
        $joursFerie = (int)$row["joursferie"];
        $bases["salaire de base"] = $row["salaireDeBase"];
        $bases["salaire mensuel"] = $row["salaireMensuel"];
        $bases["salaire brut imposable"] = $brutImposable;
        $salaireMonsuelTot += $bases["salaire mensuel"];
        $salaireHoraire = $row["salaireHoraire"];
        $salaireHoraireTot += $salaireHoraire;

        $rubs = $row["rubs"];

        foreach (explode(";", $rubs) as $r) {
            $base = 0;
            //print $rub."<br>";
            $rub = explode(":", $r);
            $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "Paie_Rub WHERE rub=" . $rub[0];
            $res = $db->query($sql);
            if ($rub[1] == "brutGlobal") {
                $brutGlobal = $rub[2];
                $brutGlobalTot += $brutGlobal;
            }
            // if ($rub[1] == "arrondiPrecdent") {
            //     $prev_arrondi = $rub[2];
            //     $prev_arrondiTot += $prev_arrondi;
            // }
            // if ($rub[1] == "arrondiEnCours") {
            //     $arrondi = $rub[2];
            //     $arrondiTot += $arrondi;
            // }
            if ($rub[1] == "chargeFamille") {
                $chargeFamille = $rub[2];
                $chargeFamilleTot += $chargeFamille;
            }
            if ($res->num_rows == 0) {
                $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "Paie_HourSupp WHERE rub=" . $rub[0];
                $res = $db->query($sql);
            }
            if ($res->num_rows == 0) {
                $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "Paie_Rubriques WHERE rub=" . $rub[0];
                $res = $db->query($sql);
            }
            if ($res->num_rows > 0 && $rub[2] != 0) {
                $param = $res->fetch_assoc();

                if ($rub[1] == "enBrut") {
                    $apayer = $rub[2];
                    if ($rub[0] === '1') {
                        $base = $row["salaireMensuel"];
                        $rubBases[$rub[0]] = !isset($rubBases[$rub[0]]) ? $base : $rubBases[$rub[0]] + $base;
                        $baseTot += $bases["salaire mensuel"];
                    }
                    if ($rub[0] === '25') {
                        $base = $rub[3];
                        $rubBases[$rub[0]] = !isset($rubBases[$rub[0]]) ? $base : $rubBases[$rub[0]] + $base;
                        $baseTot += $base;
                    }

                    $enBrutsRubs[$rub[0]] = array("rub" => $rub[0], "designation" => $param["designation"]);
                    $enBruts[$rub[0]] += $apayer;
                    $debitTot += $apayer;
                } else if ($rub[1] == "cotisation" && $Rubs[$rub[0]]["surBulletin"]) {
                    $base = $bases[$Rubs[$rub[0]]['base']];
                    if ($Rubs[$rub[0]]['plafonne']) {
                        $basePlafond = ($Rubs[$rub[0]]['plafond'] * 100) / $Rubs[$rub[0]]['percentage'];
                        if ($base > $basePlafond)
                            $base = $basePlafond;
                    }
                    $rubBases[$rub[0]] = !isset($rubBases[$rub[0]]) ? $base : $rubBases[$rub[0]] + $base;
                    $aretenu = $rub[2];
                    $creditTot += abs($aretenu);
                    $cotisations[$rub[0]] += $aretenu;
                    $cotisationsRubs[$rub[0]] = array("rub" => $rub[0], "designation" => $param["designation"]);
                    $baseTot += $base;
                } else if ($rub[1] == "pasEnBrut") {
                    $apayer = $rub[2];
                    $pasEnBrutRubs[$rub[0]] = array("rub" => $rub[0], "designation" => $param["designation"]);
                    $pasEnBruts[$rub[0]] += $apayer;
                    $debitTot += $apayer;
                }
            }
        }
    }

    $i++;
}
$creditTot += $totalNetTot;
$creditTot += $irNetTot;
$baseTot += $irbase;
$baseEnciente = 4;

asort($enBrutsRubs);
asort($pasEnBrutRubs);
asort($cotisationsRubs);
