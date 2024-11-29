<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

define('TOKEN_URL', 'https://gateway.apiserpro.serpro.gov.br/token');
define('VALIDATION_URL', 'https://gateway.apiserpro.serpro.gov.br/datavalid-demonstracao/v4/pf-facial');

// Credenciais de acesso
define('CONSUMER_KEY', 'djaR21PGoYp1iyK2n2ACOH9REdUb'); // Substitua pelo seu Consumer Key
define('CONSUMER_SECRET', 'ObRsAJWOL4fv2Tp27D1vd8fB3Ote'); // Substitua pelo seu Consumer Secret

// Função para obter o token de autenticação
function getAuthToken()
{
    $client = new Client(['verify' => false]); // 'verify' => false desativa a verificação SSL, útil para testes locais.

    try {
        $credentials = base64_encode(CONSUMER_KEY . ':' . CONSUMER_SECRET);
        $response = $client->post(TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . $credentials,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'form_params' => [
                'grant_type' => 'client_credentials'
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        if (isset($data['access_token'])) {
            return $data['access_token'];
        } else {
            throw new Exception('Token não encontrado na resposta.');
        }
    } catch (RequestException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

// Função para validar a imagem
function validateImage($cpf, $imageBase64, $authToken)
{
    $client = new Client(['verify' => false]);

    try {
        $response = $client->post(VALIDATION_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $authToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'cpf' => $cpf,
                'validacao' => [
                    'biometria_facial' => [
                        'base64' => $imageBase64,
                        'vivacidade' => true
                    ]
                ]
            ]
        ]);

        return json_decode($response->getBody(), true);
    } catch (RequestException $e) {
        return ['error' => true, 'message' => $e->getMessage()];
    }
}

// Receber dados do frontend
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['cpf']) || !isset($data['image'])) {
    echo json_encode(['success' => false, 'message' => 'CPF ou imagem não fornecidos.']);
    exit;
}

// Obter o token de autenticação
$authToken = getAuthToken();

if (isset($authToken['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao autenticar.', 'details' => $authToken['message']]);
    exit;
}

// Validar a imagem
$validationResponse = validateImage($data['cpf'], $data['image'], $authToken);

if (isset($validationResponse['error'])) {
    echo json_encode(['success' => false, 'message' => 'Erro ao validar imagem.', 'details' => $validationResponse['message']]);
    exit;
}

// Retornar o resultado da validação
echo json_encode(['success' => true, 'data' => $validationResponse]);
