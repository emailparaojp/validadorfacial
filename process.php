<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Intervention\Image\ImageManagerStatic as Image;

const LOGIN_URL = 'http://34.203.73.5:7000/login';
const VALIDATION_URL = 'http://34.203.73.5:7000/serpro/face/compares';

// Credenciais de login
const USERNAME = '#';
const PASSWORD = "#";

// Função para obter o token de autenticação
function getAuthToken()
{
    $client = new Client();

    try {
        $response = $client->post(LOGIN_URL, [
            'headers' => ['Accept' => 'application/json'],
            'json' => [
                'usuario' => USERNAME,
                'senha' => PASSWORD
            ]
        ]);

        $data = json_decode($response->getBody(), true);

        if (isset($data['token'])) {
            return $data['token'];
        } else {
            throw new Exception('Token não encontrado na resposta.');
        }
    } catch (RequestException $e) {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 'Sem código de status';
        $errorDetails = $response ? $response->getBody()->getContents() : 'Sem detalhes adicionais.';
        return [
            'error' => true,
            'status_code' => $statusCode,
            'message' => $e->getMessage(),
            'details' => $errorDetails
        ];
    }
}

// Função para validar a imagem via API
function validateImage($cpf, $imageBase64, $authToken)
{
    $client = new Client(['verify' => false]);

    try {
        $response = $client->post(VALIDATION_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $authToken,
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'cpf' => $cpf,
                'validacao' => [
                    'biometria_facial' => [
                        'vivacidade' => true,
                        'formato' => 'PNG',
                        'base64' => $imageBase64
                    ]
                ]
            ]
        ]);

        return [
            'success' => true,
            'status_code' => $response->getStatusCode(),
            'data' => json_decode($response->getBody(), true)
        ];
    } catch (RequestException $e) {
        $response = $e->getResponse();
        $statusCode = $response ? $response->getStatusCode() : 'Sem código de status';
        $errorDetails = $response ? json_decode($response->getBody()->getContents(), true) : null;

        $apiMessage = json_decode(isset($errorDetails['message']) ? $errorDetails['message'] : '', true);

        return [
            'error' => true,
            'status_code' => $statusCode,
            'message' => isset($apiMessage['code']) ? $apiMessage['code'] : 'Erro desconhecido',
            'description' => getErrorDescription(isset($apiMessage['code']) ? $apiMessage['code'] : null),
            'details' => $errorDetails
        ];
    }
}

// Função para processar a imagem (redimensionar, validar formato e tamanho)
function processImage($imageBase64)
{
    $imageData = base64_decode($imageBase64);
    $image = Image::make($imageData);

    if ($image->width() < 250 || $image->height() < 250) {
        throw new Exception('A resolução mínima da imagem deve ser 250 x 250 pixels.');
    }

    $image->resize(750, 750, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });

    $image->encode('png');

    if (strlen((string) $image) > 3 * 1024 * 1024) { // 3MB
        throw new Exception('O tamanho da imagem não pode exceder 3 MB.');
    }

    return base64_encode((string) $image);
}

// Função para mapear códigos de erro para mensagens mais amigáveis
function getErrorDescription($errorCode)
{
    $errorDescriptions = [
        'DV040' => 'Imagem da face não encontrada nas bases. O CPF utilizado na validação não possui cadastro de imagem da face na base de dados biométrica.',
        'DV042' => 'Tamanho da imagem da face inválido. Verifique os requisitos mínimos de tamanho da imagem.',
    ];

    return isset($errorDescriptions[$errorCode]) ? $errorDescriptions[$errorCode] : 'Erro desconhecido. Consulte a documentação para mais detalhes.';
}

// Receber dados do frontend
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['cpf']) || !isset($data['image'])) {
    echo json_encode(['success' => false, 'message' => 'CPF ou imagem não fornecidos.']);
    exit;
}

$cpf = $data['cpf'];
if (!preg_match('/^\d{11}$/', $cpf)) {
    echo json_encode(['success' => false, 'message' => 'CPF inválido. Certifique-se de que ele contém apenas números e 11 dígitos.']);
    exit;
}

try {
    $processedImageBase64 = processImage($data['image']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$authResponse = getAuthToken();

if (isset($authResponse['error'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao autenticar na API.',
        'status_code' => $authResponse['status_code'],
        'details' => $authResponse['details']
    ]);
    exit;
}

$authToken = $authResponse;

$validationResponse = validateImage($cpf, $processedImageBase64, $authToken);

if (isset($validationResponse['error'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao validar a imagem.',
        'status_code' => $validationResponse['status_code'],
        'description' => $validationResponse['description'],
        'details' => $validationResponse['details']
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'status_code' => $validationResponse['status_code'],
    'data' => $validationResponse['data']
]);
