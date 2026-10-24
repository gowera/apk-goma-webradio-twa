<?php
define('ABSPATH',__DIR__); require __DIR__.'/../includes/rules.php';
function check($actual,$expected,$name) { if ($actual !== $expected) throw new Exception($name); echo "PASS $name\n"; }
$r=GWR_Rules::defaults();
check($r['welcome'],30,'Bonus de bienvenue : 30 points');
check($r['read'],3,'Article lu : 3 points');
check($r['share_app'],7,'Partage application : 7 points');
check($r['share_article'],6,'Partage article : 6 points');
check($r['listen'],30,'Heure radio : 30 points');
check(GWR_Rules::hours(3599),0,'Aucun point avant 60 minutes');
check(GWR_Rules::hours(3600),1,'Une heure à exactement 60 minutes');
check(GWR_Rules::hours(7200),2,'Deux heures complètes');
check(GWR_Rules::listening(300,91),0,'Interruption longue annule heure incomplète');
check(GWR_Rules::listening(300,30),330,'Temps serveur normal');
check(GWR_Rules::listening(300,60),345,'Plafond battement empêche gonflement durée');
