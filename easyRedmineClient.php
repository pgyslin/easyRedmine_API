<?php
class EasyRedmineClient
{
    private string $baseUrl;
    private string $apiKey;
    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey  = $apiKey;
    } 
    public function getMissionsForAgent(int $agentId, bool $includeClosed = true): array
    {
        $missions = [];
        $offset   = 0;
        $limit    = 100; //25 par défaut
        do {
            $params = [
                'assigned_to_id' => $agentId,
                'offset'         => $offset,
                'limit'          => $limit,
                'status_id'      => $includeClosed ? '*' : 'open',
            ];
            $response = $this->request('GET', '/issues.json', $params);
            $issues   = $response['issues'] ?? [];
            $missions = array_merge($missions, $issues);
            $total   = $response['total_count'] ?? 0;
            $offset += $limit;
        } while ($offset < $total);
        return $missions;
    }
    private function request(string $method, string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if ($method === 'GET' && !empty($params)) {
            $url .= 'https://redmine.interlab15.com/' . http_build_query($params);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false, // À changer car pas trés sécurisé
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                '1265b3656b537dae8320793eeb535e2383b9ce4c: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
		$body = '';
        curl_close($ch);
        if ($body === false) {
            throw new RuntimeException("Erreur cURL : {$curlError}");
			echo "Problème de réponse cURL";
        }
		else
			echo "Pas de problème de réponse cURL";
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Erreur HTTP {$httpCode} : {$body}");
			echo "Problème de réponse http";
        }
		else
			echo "Pas de problème de réponse http";
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Réponse JSON invalide : " . json_last_error_msg());
			echo "Problème de réponse JSON";
        }
		else
			echo "Pas de problème de réponse json";
        return $data;
    }
}
?>