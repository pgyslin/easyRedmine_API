<?php
//  TABLEAU DE BORD INTERSCIENCE — Projets->Agents->Missions assignées

//===================== CONFIGURATION =====================
const REDMINE_URL = 'https://redmine.interlab15.com';            //SANS slash final
const API_KEY     = '36888b38f8e7834191265a37d61296a75f5d3a0b';  //votre clé
const DEBUG       = false;  //true pour diagnostiquer, false en prod
const PER_PAGE    = 100;    //tâches récupérées par requête (max 100 côté Redmine,si on met plus ça reste à 100 et prends juste plus de temps)
const MAX_PAGES   = 20;     //garde-fou (20 x 100 = 2000 tâches max balayées, actuellement 1609 présentent, plus il y a de tâches plus ce sera lent)
const OPEN_ONLY   = true;   //true = seulement les tâches ouvertes (comme votre vue)
//========================================================



//Appel GET à l'API Redmine, renvoie [data, httpCode, erreurCurl, urlAppellée].
function redmineGet(string $path, array $params = []): array
{
    $params['key'] = API_KEY;
    $url = REDMINE_URL . $path . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,     //timeout en secondes (prends environ 40-50s pour tout afficher)  
        CURLOPT_FOLLOWLOCATION => true,    //suit les redirections
        CURLOPT_SSL_VERIFYPEER => false,   //true = sécurisé/utilise le protocole SSL, false = pas sécu
        CURLOPT_HTTPHEADER     => [
            'X-Redmine-API-Key: ' . API_KEY, //double sécurité : header + param
            'Accept: application/json',
        ],
    ]);

    $body     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $data = json_decode($body ?: 'null', true);

    return [$data, $httpCode, $curlErr, $url, $body];
}


//Récupère ttes les tâches et projets en paginant.
//$key = 'issues' ou 'projects' selon le type de requête.

function fetchAllIssues(): array
{
    $all   = [];
    $debug = [];
    $offset = 0;

    for ($page = 0; $page < MAX_PAGES; $page++) {
        $params = $baseParams + [
            'offset' => $offset,
            'limit'  => PER_PAGE];
            [$data, $http, $err, $url, $raw] = redmineGet($path,$params);

        //Log de debug pour CETTE page
        $debug[] = [
            'page'       => $page + 1,
            'url'        => preg_replace('/key=[^&]+/', 'key=***', $url),
            'http'       => $http,
            'curl_error' => $err,
            'received'   => is_array($data) && isset($data[$key]) ? count($data[$key]) : 0,
            'total_count'=> is_array($data) && isset($data['total_count']) ? $data['total_count'] : 'n/a',
            'raw_snippet'=> $err || $http !== 200 ? substr((string)$raw, 0, 300) : null,
        ];

        //Si erreur ou pas d'issues, on arrête
        if ($http !== 200 || !is_array($data) || empty($data[$key])) {
            break;
        }

        $all = array_merge($all, $data[$key]);

        $total = $data['total_count'] ?? count($all);
        $offset += PER_PAGE;
        if ($offset >= $total) {
            break; //on a tout récupéré
        }
    }

    return $all;
}


//récupére les projets
function fetchProject(array &$debug): array
{
    return fetchAllIssues('/projects.json', [], 'projects', $debug);
}

//récupére les membres des projets
function fetchProjectMembers(int $projectId, array &$debug): array
{
    $rows = fetchAllIssues("/projects/$projectId/memberships.json", [], 'memberships', $debug);
    $members = [];
    foreach($rows as $m) {
        if (isset($m['user']['id'])) {
            $members[$m['user']['id']] = $m['user'];
        }
    }
    returrn $members;
}

//récupére les tâches d'un projets
function fetchProjectIssues(int $projectId, array &$debug): array
{
    $params = [
        'project_id' => $projectId,
        'status_id' => OPEN_ONLY ? 'open' : '*',
        'sort' => 'updated_on:desc',
    return fetchAll("/issues.json", $params, 'issues', $debug);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$debugLog = [];
$projects = fetchProject($debugLog);

$tree = []; //struct d'affichage
$totalMissions = 0;
$agentsIds = []; //pour compter les agents distincts

//dans chaque groupe
foreach ($projects as $proj) {
    $pid = (int)$proj['id'];
    $pname = $proj['name'];

    //Regroupe les tâches par agent (destinataire).
    //Les tâches sans destinataire vont dans un groupe "Non assigné"
    $agents = [];
    foreach ($issues as $iss) {
        if (isset($iss['assigned_to']['id'])) {
            $aid   = $iss['assigned_to']['id'];
            $aname = $iss['assigned_to']['name'];
        } else {
            $aid   = 0;
            $aname = 'Non assigné';
        }
        if (!isset($agents[$aid])) {
            $agents[$aid] = ['id' => $aid, 'name' => $aname, 'issues' => []];
        }
        $agents[$aid]['issues'][] = $iss;
        if ($aid !== 0) 
        {
            $agentsIds[$aid] = true; //pour compter les agents distincts
        }
    }

    //Pour les membres sans tâches assignées 
    //À enlever/commenter si on ne veut pas les lister
    foreach ($members as $mid => $m) {
        if (!isset($agents[$mid])) {
            $agents[$mid] = ['id' => $mid, 'name' => $m['name'], 'issues' => []];
        }
        $agentsIds[$mid] = true;
    }


    //Tri : plus de tâches d'abord
    uasort($agents, fn($a, $b) => count($b['issues']) <=> count($a['issues']));
    $totalMissions += count($issues);

    //projetc vide 
    if (!empty($agents)) {
        $tree[] = [
            'id' => $pid,
            'name' => $pname, 
            'agents' => $agents,
            'count' => count($issues),
        ];
    }
}








}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

//html 
[$issues, $debugLog] = fetchAllIssues();
$agents = groupByAgent($issues);

$totalAgents   = count(array_filter($agents, fn($a) => $a['id'] !== 0));
$totalMissions = count($issues);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tableau de bord · InterScience</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#16181d; color:#e6e8ec; }
  header { background:linear-gradient(120deg,#1b3a8f,#1f56c2); padding:18px 28px; display:flex; align-items:center; justify-content:space-between; }
  .brand { display:flex; align-items:center; gap:14px; }
  .logo { width:46px; height:46px; border-radius:10px; background:#0e2a6b; display:flex; align-items:center; justify-content:center; font-weight:700; color:#fff; }
  .brand h1 { font-size:20px; font-weight:700; }
  .stats { display:flex; gap:34px; text-align:center; }
  .stats .num { font-size:26px; font-weight:700; }
  .stats .lbl { font-size:12px; opacity:.8; }
  main { padding:26px 28px; max-width:1200px; margin:0 auto; }
  h2 { color:#5b8def; font-size:18px; margin-bottom:18px; }
  .agent { background:#1e2128; border:1px solid #2a2e37; border-radius:12px; margin-bottom:16px; overflow:hidden; }
  .agent-head { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; cursor:pointer; background:#22262f; }
  .agent-head:hover { background:#262b35; }
  .agent-name { font-weight:600; font-size:15px; }
  .badge { background:#2d62d8; color:#fff; border-radius:20px; padding:3px 12px; font-size:13px; font-weight:600; }
  .missions { padding:6px 18px 14px; }
  .mission { display:grid; grid-template-columns: 70px 130px 150px 1fr 110px; gap:12px; align-items:center; padding:9px 6px; border-bottom:1px solid #262a33; font-size:13px; }
  .mission:last-child { border-bottom:none; }
  .mid a { color:#6ea0ff; text-decoration:none; }
  .tag { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; background:#33384a; }
  .st { font-weight:600; }
  .due.late { color:#ff6b6b; font-weight:600; }
  .empty { text-align:center; padding:50px; color:#8a8f99; }
  .debug { background:#0d0f13; border:1px solid #303642; border-radius:10px; padding:14px 18px; margin-bottom:22px; font-family:ui-monospace,monospace; font-size:12px; color:#9fb3c8; overflow:auto; }
  .debug b { color:#ffd479; }
  .debug .ok { color:#5ad27a; }
  .debug .err { color:#ff6b6b; }
  table.dbg { width:100%; border-collapse:collapse; margin-top:8px; }
  table.dbg td, table.dbg th { border:1px solid #2a2f3a; padding:4px 8px; text-align:left; }
</style>
</head>
<body>
<header>
  <div class="brand">
    <div class="logo">IS</div>
    <h1>Tableau de bord · InterScience</h1>
     <a class="btn" href="index.html" >test</a>
  </div>
  <div class="stats">
    <div><div class="num"><?= $totalAgents ?></div><div class="lbl">Agents</div></div>
    <div><div class="num"><?= $totalMissions ?></div><div class="lbl">Missions</div></div>
  </div>
</header>

<main>

<?php if (DEBUG): ?>
  <div class="debug">
    <b>MODE DEBUG ACTIF</b> (passez DEBUG = false pour le masquer)<br><br>
    URL de base : <?= h(REDMINE_URL) ?><br>
    Filtre : <?= OPEN_ONLY ? 'tâches ouvertes (status=open)' : 'toutes les tâches' ?><br>
    Total tâches récupérées : <b><?= $totalMissions ?></b> &nbsp;|&nbsp; Agents distincts : <b><?= $totalAgents ?></b><br>
    <table class="dbg">
      <tr><th>Page</th><th>HTTP</th><th>Reçues</th><th>total_count</th><th>Erreur cURL</th><th>Aperçu si erreur</th></tr>
      <?php foreach ($debugLog as $d): ?>
        <tr>
          <td><?= $d['page'] ?></td>
          <td class="<?= $d['http']===200?'ok':'err' ?>"><?= h($d['http']) ?></td>
          <td><?= h($d['received']) ?></td>
          <td><?= h($d['total_count']) ?></td>
          <td class="<?= $d['curl_error']?'err':'' ?>"><?= h($d['curl_error'] ?: '—') ?></td>
          <td><?= h($d['raw_snippet'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php if ($totalMissions === 0): ?>
      <br><span class="err">Aucune tâche reçue.</span>
      Si HTTP = 401 → clé API invalide ou API REST désactivée.
      Si HTTP = 200 mais 0 reçue → le filtre status renvoie vide (essayez OPEN_ONLY = false).
      Si "Erreur cURL" rempli → problème réseau/SSL (essayez CURLOPT_SSL_VERIFYPEER = false).
    <?php endif; ?>
  </div>
<?php endif; ?>

  <h2>Agents &amp; missions assignées</h2>

  <?php if ($totalMissions === 0): ?>
    <div class="empty">Aucune mission assignée trouvée.</div>
  <?php else: ?>
    <?php foreach ($agents as $agent): ?>
      <div class="agent">
        <div class="agent-head" onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display==='none'?'block':'none'">
          <span class="agent-name"><?= h($agent['name']) ?></span>
          <span class="badge"><?= count($agent[$key]) ?> mission<?= count($agent[$key])>1?'s':'' ?></span>
        </div>
        <div class="missions" style="display:none">
          <?php foreach ($agent[$key] as $iss): 
              $due = $iss['due_date'] ?? null;
              $late = $due && strtotime($due) < time();
          ?>
            <div class="mission">
              <span class="mid"><a href="<?= h(REDMINE_URL) ?>/issues/<?= h($iss['id']) ?>" target="_blank">#<?= h($iss['id']) ?></a></span>
              <span class="tag"><?= h($iss['tracker']['name'] ?? '') ?></span>
              <span class="st"><?= h($iss['status']['name'] ?? '') ?></span>
              <span><?= h($iss['subject'] ?? '') ?></span>
              <span class="due <?= $late?'late':'' ?>"><?= $due ? date('d/m/Y', strtotime($due)) : '—' ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</main>
</body>
</html>
