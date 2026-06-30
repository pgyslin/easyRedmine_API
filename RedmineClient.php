<?php
 // RedmineClient — wrapper léger pour l'API REST easyRedmine.
  //L'API Redmine s'authentifie via l'en-tête X-Redmine-API-Key.
  //Toutes les ressources sont accessibles en .json :
  //issues.json, /users.json, /projects.json, etc.
 
class RedmineClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private bool $sslVerify;

    public function __construct(array $config)
    {
        $this->baseUrl   = rtrim($config['base_url'], '/');
        $this->apiKey    = $config['api_key'];
        $this->timeout   = $config['timeout'] ?? 15;
        $this->sslVerify = $config['ssl_verify'] ?? true;
    }

      //Requête GET générique.
      //@param string $path  ex: '/issues.json'
      //@param array  $query ex: ['assigned_to_id' => 5, 'status_id' => 'open']

    public function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    // Requête POST (création)
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $this->baseUrl . $path, $body);
    }

    // Requête PUT (modification)
    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $this->baseUrl . $path, $body);
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);

        $headers = [
            'd8dfdec2f5562746809135a03e87b6cf439f1541' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Erreur cURL : $error");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Erreur API Redmine (HTTP $httpCode) : $response");
        }

        // PUT renvoie souvent un corps vide
        if ($response === '') {
            return ['success' => true];
        }

        return json_decode($response, true) ?? [];
    }
}