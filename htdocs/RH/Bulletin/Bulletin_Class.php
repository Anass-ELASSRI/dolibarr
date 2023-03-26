<?php
global $year, $month;

$params = '';
$salaireParams = '';

//Get Parameters from database
$sql = "SELECT * FROM llx_Paie_bdpParameters";
$res = $db->query($sql);
$params = ((object)($res))->fetch_assoc();

//Get user salaire informations from database
$sql = "SELECT * from llx_Paie_UserInfo WHERE userid=" . $object->rowid;
$res = $db->query($sql);
if ($res->num_rows > 0) {
    $salaireParams = $res->fetch_assoc();
}

// see if it's clotured
$cloture = 0;
$avance = 0;
$sql1 = "SELECT cloture, avance FROM llx_Paie_MonthDeclaration WHERE userid=$object->rowid AND year=$year AND month=$month";
$res1 = $db->query($sql1);
if ($res1) {
    $row1 = $res1->fetch_assoc();
    $cloture = $row1["cloture"] > 0 ? $row1["cloture"] : 0;
    $avance = $row1["avance"] > 0 ? $row1["avance"] : 0;
}
if ($cloture > 0) {
    return;
}
//peroide
$periode = sprintf("%02d", $month) . '/' . $year;
if ($cloture == 0) {

    //Rubs for declaration
    $rubs = "";

    //Get working time on this month
    $Hours = 0;
    $primeDancien = 0;
    $diff = 0;
    $dt = 0;
    $dateemployment = 0;
    $primeDancienPercentage = 0;
    $situation = 0;
    $enfants = 0;
    $chargeFamilleTaux = 0;
    $chargeFamille = 0;
    $type = 0;
    $congeDays = 0;
    $workingYears = 0;
    $joursFerie = 0;
    $Taux = 0;
    $salaireHoraire = 0;
    $salaireHoraire = 0;

    //Get user extra informations from database
    $sql = "SELECT situation, enfants, idpointage, matricule FROM " . MAIN_DB_PREFIX . "user_extrafields WHERE fk_object=" . $object->rowid;
    $res = $db->query($sql);
    $extrafields = ((object)($res))->fetch_assoc();


    $situation = ($extrafields['situation'] == '1') ? "MARIE" : (($extrafields['situation'] == '2') ? "CELIBATAIRE" : "DIVORCE");
    $enfants = $extrafields['enfants'] > $params["maxChildrens"] ? $params["maxChildrens"] : (int)$extrafields['enfants'];

    $chargeFamilleTaux = (($extrafields['situation'] == 1) && $object->gender == "man") ? 1 : 0;
    $chargeFamilleTaux += $enfants;
    $chargeFamille = $chargeFamilleTaux * $params["primDenfan"];
    $rubs .= getRebrique("chargefamille") . ":chargeFamille:$chargeFamille" . ";";
    $type = $salaireParams["type"];

    $workingdays = 26;
    $workingHours = 0;
    $sql = "SELECT workingDays, joursferie, joursconge, workingHours FROM llx_Paie_MonthDeclaration WHERE userid=$object->rowid AND month=$month AND year = $year";
    $res = $db->query($sql);
    if (((object)$res)->num_rows > 0) {
        $row = ((object)$res)->fetch_assoc();
        $workingdays = (float)$row["workingDays"];
        $workingHours = (float)$row["workingHours"];
        $congeDays = (float)$row["joursconge"];
        $joursFerie = (int)$row["joursferie"];
    }

    if ($type == 'mensuel') //Mensuel
    {
        $smig = $params["smigHoraire"] * $workingdaysdeclaré * ($params["hoursMonsuele"] / $params["workingDays"]);
        $object->salary = $object->salary < $smig ? $smig : $object->salary;

        $bases["salaire de base"] = (float)$object->salary;
        ////////////////////
        $dt = date("Y-m-d", $object->dateemployment);
        $dateemployment = explode('-', $dt)[2] . '-' . explode('-', $dt)[1] . '-' . explode('-', $dt)[0];
        $diff = date_diff(date_create($dateemployment), date_create("$year-$month-30"));
        $workingYears = $diff->format("%a") / 365;



        if ($workingdays > ($params["workingDays"] - $congeDays - $joursFerie)) {
            $workingdays = $params["workingDays"] - $congeDays - $joursFerie;
        }
        $Taux = $workingdays;

        //get working days
        $workingdaysdeclaré = $workingdays + $congeDays + $joursFerie;

        // calculate smig
        $bases["salaire mensuel"] = (float)(($object->salary / $params["workingDays"]) * $workingdays);
        // $bases["salaire mensuel"] = 884.69;

        $salaireHoraire = (float)($bases["salaire de base"] / $params["hoursMonsuele"]);

        $rubs .= getRebrique("salaireMensuel") . ":enBrut:" . $bases["salaire mensuel"] . ";";
    } else if ($type == 'horaire') //Journalier or Horaire
    {
        $object->thm = ($object->thm < $params["smigHoraire"]) ? $params["smigHoraire"] : $object->thm;
        $bases["salaire de base"] = (float)($object->thm * $params["hoursMonsuele"]);

        if (($workingHours + ($congeDays * ($params["hoursMonsuele"] / $params["workingDays"])) + ($joursFerie * ($params["hoursMonsuele"] / $params["workingDays"]))) > $params["hoursMonsuele"]) {
            $workingHours = (int) ($params["hoursMonsuele"] - ($congeDays * ($params["hoursMonsuele"] / $params["workingDays"])) - ($joursFerie * ($params["hoursMonsuele"] / $params["workingDays"])));
        }
        $Taux = $workingHours;

        $yearHours = 1;
        $sql = "SELECT sum(workingHours) FROM llx_Paie_MonthDeclaration WHERE userid=$object->rowid";
        $res = $db->query($sql);
        if (((object)$res)->num_rows > 0) {
            $row = ((object)$res)->fetch_assoc();
            $yearHours = (int)$row["workingHours"];
        }
        $workingYears = (int)($yearHours / ($params["hoursMonsuele"] * 12 + $conge));

        //get working days
        $workingdays = $Taux  / ($params["hoursMonsuele"] / $params["workingDays"]);
        $workingdaysdeclaré = $workingdays + $congeDays + $joursFerie;
        if ($workingdaysdeclaré > ($params["workingDays"] - $congeDays - $joursFerie)) {
            $workingdaysdeclaré = $params["workingDays"] - $congeDays - $joursFerie;
        }
        $workingdaysdeclaré = round($workingdaysdeclaré);
        // calculate smig
        $bases["salaire mensuel"] = (float)($object->thm * $Taux);
        $salaireHoraire = $object->thm;

        $rubs .= getRebrique("salaireHoraire") . ":enBrut:" . $bases["salaire mensuel"] . ";";
    }

    //Get Prime D'ANCIENNETE from database by the workingYears
    $sql = "SELECT percentPrimDancien FROM llx_Paie_PrimDancienParameters WHERE (" . $workingYears . ">de and " . $workingYears . "<=a) OR (" . $workingYears . ">de and a = '+')";
    $res = $db->query($sql);

    if ($res->num_rows > 0) {
        $primeDancienPercentage = ((object)($res))->fetch_assoc()["percentPrimDancien"];
    }

    //CONGE
    $soldeConge = ($bases["salaire de base"] / $params["workingDays"]) * $congeDays;
    $rubs .= getRebrique("congePaye") . ":enBrut:" . $soldeConge . ";";

    //Jours Férie
    $soldeferie = ($bases["salaire de base"] / $params["workingDays"]) * $joursFerie;
    $rubs .= getRebrique("joursferie") . ":enBrut:" . $soldeferie . ";";


    //Les hours supp
    $soldeHoursSup = 0;
    $hrs = array();
    $sql = "SELECT h.rub, h.designation, h.percentHourSupp, d.nhours FROM llx_Paie_HourSupp h, llx_Paie_HourSuppDeclaration d WHERE h.rub=d.rub AND d.userid=$object->rowid AND d.year=$year AND d.month=$month";
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        while ($hr = ((object)($res))->fetch_assoc()) {
            $rub = $hr["rub"];
            $designation = $hr["designation"];
            $percentage = $hr["percentHourSupp"] + 100;
            $nhours = $hr["nhours"];
            $apayer = $salaireHoraire * $nhours * $percentage / 100;

            $soldeHoursSup += $apayer;

            if ($apayer > 0) {
                $hrs[] = array("rub" => $rub, "designation" => $designation, "nombre" => $nhours, "base" => $salaireHoraire, "taux" => $percentage, "apayer" => $apayer, "aretenu" => "");
                $rubs .= "$rub:enBrut:$apayer" . ";";
            }
        }
    }

    //PRIME D'ANCIEN
    $bases['primeDancien'] = $bases["salaire mensuel"] + $soldeConge + $soldeferie + $soldeHoursSup;
    $primeDancien = $primeDancienPercentage * $bases['primeDancien'] / 100;
    $rubs .= getRebrique("primeDancien") . ":enBrut:" . $primeDancien . ":" . $bases['primeDancien'] . ";";

    //Les Bruts
    $brutGlobal = $bases["salaire mensuel"] + $primeDancien + $soldeConge + $soldeferie + $soldeHoursSup;
    $brutImposable = $brutGlobal;

    //print sizeof($hrs);

    $enBruts = array();
    $totalRetenu = 0;
    $retenueFromBrut = 0;


    //Get les rubriques en brut global
    $sql = "SELECT * FROM llx_Paie_Rub WHERE enBrut=1";
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        while ($param = ((object)($res))->fetch_assoc()) {
            $apayer = 0;
            $aretenu = 0;
            //if it's prime or indemnite add from fiche emploiyee
            if ($param["auFiche"]) {
                //get value of it
                $sql = "SELECT amount, checked FROM llx_Paie_UserParameters  WHERE rub=" . $param['rub'] . " AND userid=$object->rowid";
                $resFiche = $db->query($sql);
                if ($resFiche->num_rows > 0) {
                    $fiche = $resFiche->fetch_assoc();
                    //STC
                    if ($param['rub'] == 6) {
                        $apayer = ($bases["salaire de base"] / $params["workingDays"]) * (float)$fiche["amount"];
                        $base = $bases["salaire de base"];
                        $Tauxr = (float)$fiche["amount"];
                    }
                    //if it's calculable
                    else if ($param["calcule"] == 1 && $fiche["checked"] == 1) {
                        $base = $bases[$param["base"]];
                        $Tauxr = $param["percentage"] . "%";
                        $apayer = (float)($base * $param["percentage"] / 100);
                        if ($param["plafonne"] == 1) {
                            $base = ($apayer > $param["plafond"]) ? $param["plafond"] / $param["percentage"] * 100 : $base;
                        }
                        $apayer = (float)($base * $param["percentage"] / 100);
                    } else {
                        $base = "";
                        $Tauxr = "";
                        if ($param["rub"] == 5) {
                            $aretenu = (float)$fiche["amount"];
                            $retenueFromBrut += $aretenu;
                        } else {
                            $apayer = (float)$fiche["amount"];
                        }
                    }
                    //if it's depend to working days
                    if ($param["enJours"] == 1) {
                        if ($param["avecConge"] == 1) {
                            $apayer *= $workingdaysdeclaré / $params["workingDays"];
                            $base = (float)$fiche["amount"];
                            $Tauxr = $workingdaysdeclaré;
                        } else {
                            $apayer *= $workingdays / $params["workingDays"];
                            $base = (float)$fiche["amount"];
                            $Tauxr = $workingdays;
                        }
                    }
                    if ($fiche["checked"] == 1 || $fiche["amount"] > 0) {
                        if ($param["rub"] == 5) {
                            $rubs .= $param["rub"] . ":enBrut:$aretenu" . ";";
                        } else {
                            $rubs .= $param["rub"] . ":enBrut:$apayer" . ";";
                        }
                        $enBruts[] = array("rub" => $param["rub"], "designation" => $param["designation"], "nombre" => "", "base" => $base, "taux" => $Tauxr, "apayer" => $apayer, "aretenu" => $aretenu, "surbulletin" => $param["surBulletin"]);
                    }
                }
            } else {
                //if it's calculable
                if ($param["calcule"] == 1) {
                    $base = $bases[$param["base"]];
                    $Tauxr = $param["percentage"] . "%";
                    //$apayer = (float)($base * $param["percentage"] / 100);
                    $apayer = $param["percentage"] / 100 * 5;
                    if ($param["plafonne"] == 1) {
                        $base = ($apayer > $param["plafond"]) ? $param["plafond"] / $param["percentage"] * 100 : $base;
                    }
                    //$base = 14.81;
                    $apayer = (float)($base / 191 * $param["percentage"] / 100 * 5);
                } else {
                    $apayer = (float)$fiche["amount"];
                }

                $enBruts[] = array("rub" => $param["rub"], "designation" => $param["designation"], "nombre" => "5", "base" => $base / 191, "taux" => $Tauxr, "apayer" => $apayer, "aretenu" => "", "surbulletin" => $param["surBulletin"]);
                $rubs .= $param["rub"] . ":enBrut:$apayer" . ";";
            }
            $brutGlobal += $apayer;
            if ($param["imposable"] == 1) {
                if ($param["partiel"] == 1) {
                    $brutImposable += ($apayer > $param["maxFree"]) ? $apayer - $param["maxFree"] : 0;
                } else {
                    $brutImposable += $apayer;
                }
            }
        }
    }


    $brutImposable -= $retenueFromBrut;
    $brutGlobal -= $retenueFromBrut;

    $bases["salaire brut imposable"] = $brutImposable;

    $netImposable = $brutImposable;

    $cotisations = array();
    $totalCotisations = 0;
    //Get les rubriques cotisations
    $sql = "SELECT * FROM llx_Paie_Rub WHERE cotisation=1";
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        while ($param = ((object)($res))->fetch_assoc()) {

            if ($param["auFiche"]  == 1) {
                $sql1 = "SELECT checked FROM llx_Paie_UserParameters WHERE rub=" . $param["rub"] . " AND userid=" . $object->rowid;
                $res1 = $db->query($sql1);
                $checked = ((object)($res1))->fetch_assoc()["checked"];
                if ($checked != 1)
                    continue;
            }
            //if it's calculable
            $base = $bases[$param["base"]];
            // frais PROFESSIONNELS
            if ($param["rub"] == '714') {
                $param["percentage"] = $brutImposable <= 6500 ? 35 : 25;
            }
            $Tauxr = $param["percentage"] . "%";

            $aretenu = (float)($base * $param["percentage"] / 100);

            if ($param["plafonne"] == 1) {
                $base = ($aretenu > $param["plafond"]) ? $param["plafond"] / $param["percentage"] * 100 : $base;
            }
            $aretenu = (float)($base * $param["percentage"] / 100);
            $totalCotisations += $aretenu;

            $cotisations[] = array("rub" => $param["rub"], "designation" => $param["designation"], "nombre" => $brutImposable, "base" => $base, "taux" => $Tauxr, "apayer" => "", "aretenu" => $aretenu, "surbulletin" => $param["surBulletin"]);
            $rubs .= $param["rub"] . ":cotisation:" . ($aretenu * -1) . ";";

            if ($param["surBulletin"]) {
                $totalRetenu += $aretenu;
            }

            if ($param["enNetImposable"]) {
                $netImposable -= $aretenu;
            }
        }
    }

    // Les cumule
    $sql = "SELECT sum(workingDays) as workingDays, sum(joursferie) as joursferie, sum(joursconge) as joursconge, sum(netImposable) as netImposable, sum(salaireBrut) as salaireBrut, sum(ir) as ir FROM llx_Paie_MonthDeclaration WHERE userid=$object->rowid AND year=$year AND month<$month";
    $res = $db->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
        $comulWorkingDays = (float)$row["workingDays"] + (float)$row["joursferie"] + (float)$row["joursconge"];
        $comulnetImposable = (float)$row["netImposable"];
        $comulsalaireBrut = (float)$row["salaireBrut"];
        $comulIR = (float)$row["ir"];
    }

    //Get IR from database by the netImposable
    $irbase = 0;
    if (($comulWorkingDays + $workingdaysdeclaré) != 0) {
        $irbase = (($comulnetImposable + $netImposable) / ($comulWorkingDays + $workingdaysdeclaré)) * ($params["workingDays"] * 12);
    }
    $irbase = ($irbase > 0) ? $irbase : 0;

    $sql = "SELECT percentIR, deduction FROM llx_Paie_IRParameters WHERE (" . $irbase . ">=irmin and " . $irbase . "<=irmax) OR (" . $irbase . ">=irmin and irmax = '+')";
    $res = $db->query($sql);
    $ir = ((object)($res))->fetch_assoc();

    $irBrut = $ir['percentIR'] * $irbase / 100 - $ir['deduction'];
    $irNet = ($irBrut > $chargeFamille) ? $irBrut - $chargeFamille * 12 : 0;

    $irNet = ($irNet / ($params["workingDays"] * 12)) * ($comulWorkingDays + $workingdaysdeclaré);
    $irNet = $irNet - $comulIR;

    if ($irNet < 0) {
        $irNet = 0;
    }

    $totalRetenu += $irNet + $avance;
    $totalBrut = $brutGlobal;

    //Get les rubriques pas en brut global et non imposable
    $pasEnBruts = array();
    $aretenu = 0;
    $sql = "SELECT * FROM llx_Paie_Rub WHERE enBrut!=1 AND cotisation!=1 AND imposable!=1";
    $res = $db->query($sql);
    if ($res->num_rows > 0) {
        while ($param = ((object)($res))->fetch_assoc()) {
            $apayer = 0;
            //if it's prime or indemnite add from fiche emploiyee
            if ($param["auFiche"]) {
                //get value of it
                $sql = "SELECT amount, checked FROM llx_Paie_UserParameters  WHERE rub=" . $param['rub'] . " AND userid=$object->rowid";
                $resFiche = $db->query($sql);
                if ($resFiche->num_rows > 0) {
                    $fiche = $resFiche->fetch_assoc();
                    //if it's calculable
                    if ($param["calcule"] == 1 && $fiche["checked"] == 1) {
                        $base = $bases[$param["base"]];
                        $Tauxr = $param["percentage"] . "%";
                        $apayer = (float)($base * $param["percentage"] / 100);
                        if ($param["plafonne"] == 1) {
                            $base = ($apayer > $param["plafond"]) ? $param["plafond"] / $param["percentage"] * 100 : $base;
                        }
                        $apayer = (float)($base * $param["percentage"] / 100);
                    } else {
                        $base = "";
                        $Tauxr = "";
                        $apayer = (float)$fiche["amount"];
                    }
                    //if it's depend to working days
                    if ($param["enJours"] == 1) {
                        if ($param["avecConge"] == 1) {
                            $apayer *= $workingdaysdeclaré / $params["workingDays"];
                        } else {
                            $apayer *= $workingdays / $params["workingDays"];
                        }
                    }
                    if ($fiche["checked"] == 1 || $fiche["amount"] > 0) {
                        $rubs .= $param["rub"] . ":pasEnBrut:$apayer" . ";";
                        $pasEnBruts[] = array("rub" => $param["rub"], "designation" => $param["designation"], "nombre" => "", "base" => $base, "taux" => $Tauxr, "apayer" => $apayer, "aretenu" => "", "surbulletin" => $param["surBulletin"]);
                    }
                }
            } else {
                //if it's calculable
                if ($param["calcule"] == 1) {
                    $base = $bases[$param["base"]];
                    $Tauxr = $param["percentage"] . "%";
                    $apayer = (float)($base * $param["percentage"] / 100);
                    if ($param["plafonne"] == 1) {
                        $base = ($apayer > $param["plafond"]) ? $param["plafond"] / $param["percentage"] * 100 : $base;
                    }
                    $apayer = (float)($base * $param["percentage"] / 100);
                    $pasEnBruts[] = array("rub" => $param["rub"], "designation" => $param["designation"], "nombre" => "", "base" => $base, "taux" => $Tauxr, "apayer" => $apayer, "aretenu" => "", "surbulletin" => $param["surBulletin"]);
                    $rubs .= $param["rub"] . ":pasEnBrut:$apayer" . ";";
                }
                if ($avance > 0 && $param["rub"] == '902') {
                    $rubs .= $param["rub"] . ":pasEnBrut:" . ($avance * -1) . ";";
                    $pasEnBruts[] = array("rub" => $param["rub"], "designation" => $param["designation"], "nombre" => "", "base" => $base, "taux" => $Tauxr, "apayer" => "", "aretenu" => $avance, "surbulletin" => $param["surBulletin"]);
                }
            }
            $totalBrut += $apayer;
            $totalRetenu += $aretenu;
        }
    }



    $totalNet = $totalBrut - $totalRetenu;

    //declare brut global
    $rubs .= " :brutGlobal:" . $brutGlobal . ";";


    //Inset data to month declaration table
    $sql = "REPLACE INTO llx_Paie_MonthDeclaration(userid, year, month, workingDays, workingHours, joursferie, netImposable, salaireBrut, salaireNet, ir, cloture, avance, joursconge ) VALUES($object->rowid, $year, $month, $workingdays, $workingHours, $joursFerie, $netImposable, $brutImposable, $totalNet, $irNet, $cloture, $avance, $congeDays);";
    $res = $db->query($sql);
    if ($res);
    else print("<br>fail ERR: " . $sql);

    $sql = "REPLACE INTO llx_Paie_MonthDeclarationRubs(userid, year, month, type, post, situationFamiliale, enfants, salaireDeBase, salaireMensuel, salaireHoraire, rubs) 
        VALUES($object->rowid, $year, $month, '$type', '$object->job', '$situation', $enfants, " . $bases["salaire de base"] . ", " . $bases["salaire mensuel"] . ", $salaireHoraire, '$rubs');";
    $res = $db->query($sql);
    if ($res);
    else print("<br>fail ERR: " . $sql);
} else {
    $sql = "SELECT * FROM llx_Paie_MonthDeclaration m, llx_Paie_MonthDeclarationRubs r WHERE r.userid=m.userid AND r.month=m.month AND r.year = m.year AND m.userid=$object->rowid AND m.month=$month AND m.year = $year";
    $res = $db->query($sql);
    if (((object)$res)->num_rows > 0) {
        $row = $res->fetch_assoc();
        $situation = $row["situationFamiliale"];
        $type = $row["type"];
        $role = $row["post"];
        $joursFerie = (int)$row["joursferie"];
        $bases["salaire de base"] = $row["salaireDeBase"];
        $bases["salaire mensuel"] = $row["salaireMensuel"];
        $brutGlobal = $bases["salaire mensuel"];
        $rubs = $row["rubs"];

        $enBruts[] = array();
        $i = 0;

        foreach (explode(";", $rubs) as $r) {
            //print $rub."<br>";
            $rub = explode(":", $r);
            $sql = "SELECT * FROM llx_Paie_Rub WHERE rub=" . $rub[0];
            $res = $db->query($sql);
            if ($res->num_rows == 0) {
                $sql = "SELECT * FROM llx_Paie_HourSupp WHERE rub=" . $rub[0];
                $res = $db->query($sql);
            }
            if ($res->num_rows == 0) {
                $sql = "SELECT * FROM llx_Paie_Rubriques WHERE rub=" . $rub[0];
                $res = $db->query($sql);
            }
            if ($res->num_rows > 0 && $rub[2] > 0) {
                $param = $res->fetch_assoc();

                if ($rub[1] == "enBrut") {
                    $brutGlobal += $rub[2];
                    $apayer = $rub[2];
                    $enBruts[] = array("rub" => $param["rub"], "designation" => strtoupper($param["designation"]), "nombre" => "", "base" => $base, "taux" => $Tauxr, "apayer" => $apayer, "aretenu" => "", "surbulletin" => $param["surBulletin"]);
                }
            }
        }
    }
}
