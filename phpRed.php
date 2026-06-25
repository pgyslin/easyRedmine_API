<?php

require 'EasyRedmineClient.php';

$client = new EasyRedmineClient(
    'https://redmine.interlab15.com/',
    '1265b3656b537dae8320793eeb535e2383b9ce4c'
);
/*On va prendre comme exemple l'agent 5 et on veut voir les missions auquel il est assigné(C'est les missions A et E)*/
$missions = $client->getMissionsForAgent(5);

echo "L'agent n°5 a " . count($missions) . " mission(s) :\n";

foreach ($missions as $mission) {
    echo "- Mission #{$mission['id']} : {$mission['subject']}\n";
}
echo "La page fonctionne";
?>