<?php
/**
 * api-dashboard.php
 * Renvoie en JSON la liste des agents avec leurs missions assignées.
 * Appelé par le front (fetch). La clé API reste côté serveur.
 */

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/RedmineClient.php';
$config = require __DIR__ . '/config.php';

try {
    $client = new RedmineClient($config);

    // 1. Récupérer les utilisateurs (agents) actifs
    $usersResp = $client->get('/users.json', ['status' => 1, 'limit' => 100]);
    $users = $usersResp['users'] ?? [];

    // 2. Récupérer toutes les missions ouvertes en une fois

    $issuesResp = $client->get('/issues.json', [
        'status_id' => 'open',
        'limit'     => 100,
        'sort'      => 'priority:desc',
    ]);
    $issues = $issuesResp['issues'] ?? [];

    // 3. Regrouper les missions par agent assigné
    $missionsByAgent = [];
    foreach ($issues as $issue) {
        if (!isset($issue['assigned_to'])) {
            continue; // mission non assignée
        }
        $agentId = $issue['assigned_to']['id'];

        $missionsByAgent[$agentId][] = [
            'id'        => $issue['id'],
            'sujet'     => $issue['subject'],
            'priorite'  => $issue['priority']['name']   ?? '—',
            'statut'    => $issue['status']['name']      ?? '—',
            'avancement'=> $issue['done_ratio']          ?? 0,
            'echeance'  => $issue['due_date']            ?? null,
            'projet'    => $issue['project']['name']     ?? '—',
        ];
    }

    // 4. Construire la réponse : un agent + ses missions
    $dashboard = [];
    foreach ($users as $user) {
        $id = $user['id'];
        $missions = $missionsByAgent[$id] ?? [];

        // On n'affiche que les agents ayant au moins une mission
        if (empty($missions)) {
            continue;
        }

        $dashboard[] = [
            'id'       => $id,
            'nom'      => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
            'email'    => $user['mail'] ?? '',
            'nb'       => count($missions),
            'missions' => $missions,
        ];
    }

    echo json_encode([
        'success'   => true,
        'generated' => date('c'),
        'agents'    => $dashboard,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
