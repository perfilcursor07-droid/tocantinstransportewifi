<?php

namespace App\Services;

class PixQRCodeService
{
    private $pixKey;
    private $merchantName;
    private $merchantCity;
    private $currency = '986'; // BRL
    private $countryCode = 'BR';
    private $merchantCategoryCode = '0000';

    public function __construct()
    {
        $this->pixKey = config('wifi.pix.key');
        $this->merchantName = config('wifi.pix.merchant_name');
        $this->merchantCity = config('wifi.pix.merchant_city');
    }

    /**
     * Gera string EMV para QR Code PIX
     */
    public function generatePixQRCode(float $amount, string $transactionId = null): array
    {
        // Validar dados obrigatórios
        if (empty($this->pixKey)) {
            throw new \Exception('Chave PIX não configurada');
        }

        if (empty($this->merchantName)) {
            throw new \Exception('Nome do comerciante não configurado');
        }

        if (empty($this->merchantCity)) {
            throw new \Exception('Cidade do comerciante não configurada');
        }

        // Formatar valor
        $formattedAmount = number_format($amount, 2, '.', '');
        
        // Gerar location (URL fictícia - em produção usar API do banco)
        $location = $this->generatePixLocation($transactionId);
        
        // Construir campos da string EMV
        $fields = [];
        
        // Campo 00: Payload Format Indicator
        $fields['00'] = $this->formatField('00', '01');
        
        // Campo 01: Point of Initiation Method
        $fields['01'] = $this->formatField('01', '12');
        
        // Campo 26: Merchant Account Information
        $merchantInfo = $this->buildMerchantAccountInfo($location);
        $fields['26'] = $this->formatField('26', $merchantInfo);
        
        // Campo 52: Merchant Category Code
        $fields['52'] = $this->formatField('52', $this->merchantCategoryCode);
        
        // Campo 53: Transaction Currency
        $fields['53'] = $this->formatField('53', $this->currency);
        
        // Campo 54: Transaction Amount
        $fields['54'] = $this->formatField('54', $formattedAmount);
        
        // Campo 58: Country Code
        $fields['58'] = $this->formatField('58', $this->countryCode);
        
        // Campo 59: Merchant Name
        $merchantNameClean = $this->cleanString($this->merchantName, 25);
        $fields['59'] = $this->formatField('59', $merchantNameClean);
        
        // Campo 60: Merchant City
        $merchantCityClean = $this->cleanString($this->merchantCity, 15);
        $fields['60'] = $this->formatField('60', $merchantCityClean);
        
        // Campo 62: Additional Data Field Template
        $additionalData = $this->buildAdditionalData($transactionId);
        $fields['62'] = $this->formatField('62', $additionalData);
        
        // Juntar todos os campos (exceto CRC)
        $payload = implode('', $fields);
        
        // Adicionar campo 63 (CRC) placeholder
        $payload .= '6304';
        
        // Calcular CRC16-CCITT
        $crc = $this->calculateCRC16($payload);
        
        // String EMV final
        $emvString = substr($payload, 0, -4) . '63' . '04' . strtoupper($crc);
        
        
        return [
            'emv_string' => $emvString,
            'amount' => $formattedAmount,
            'transaction_id' => $transactionId,
            'location' => $location,
            'qr_code_data' => $emvString
        ];
    }

    /**
     * Gera PIX estático (chave do recebedor) para pagamento manual a motoristas.
     */
    public function generateStaticPixEmv(
        string $pixKey,
        string $recipientName,
        ?float $amount = null,
        ?string $city = null,
        ?string $reference = null,
        ?string $pixKeyType = null
    ): string {
        $key = $this->formatPixKeyForEmv($pixKey, $pixKeyType);
        $name = $this->cleanString($recipientName, 25);
        $cityClean = $this->cleanString($city ?? config('wifi.pix.merchant_city', 'PALMAS'), 15);

        $merchantInfo = $this->formatField('00', 'br.gov.bcb.pix')
            . $this->formatField('01', $key);

        $payload = $this->formatField('00', '01');
        $payload .= $this->formatField('01', '11');
        $payload .= $this->formatField('26', $merchantInfo);
        $payload .= $this->formatField('52', $this->merchantCategoryCode);
        $payload .= $this->formatField('53', $this->currency);

        if ($amount !== null && $amount > 0) {
            $payload .= $this->formatField('54', number_format($amount, 2, '.', ''));
        }

        $payload .= $this->formatField('58', $this->countryCode);
        $payload .= $this->formatField('59', $name);
        $payload .= $this->formatField('60', $cityClean);

        $ref = $reference ? preg_replace('/[^A-Za-z0-9]/', '', $reference) : '';
        $ref = substr($ref ?: 'PAG', 0, 25);
        $payload .= $this->formatField('62', $this->formatField('05', $ref));

        $payload .= '6304';
        $crc = $this->calculateCRC16($payload);

        return substr($payload, 0, -4) . '6304' . $crc;
    }

    private function formatPixKeyForEmv(string $key, ?string $pixKeyType = null): string
    {
        $type = $pixKeyType ?? \App\Models\DriverPixProfile::detectPixKeyType($key);
        $normalized = \App\Models\DriverPixProfile::normalizePixKey($key, $type);

        if ($type === 'phone') {
            $digits = preg_replace('/\D/', '', $normalized);

            if (strlen($digits) === 10 || strlen($digits) === 11) {
                return '+55' . $digits;
            }

            if (strlen($digits) === 13 && str_starts_with($digits, '55')) {
                return '+' . $digits;
            }
        }

        return $normalized;
    }

    /**
     * Formatar campo EMV
     */
    private function formatField(string $id, string $value): string
    {
        $length = str_pad(strlen($value), 2, '0', STR_PAD_LEFT);
        return $id . $length . $value;
    }

    /**
     * Construir informações da conta do comerciante (Campo 26)
     */
    private function buildMerchantAccountInfo(string $location): string
    {
        // Subcampo 00: GUI (Banco Central)
        $gui = $this->formatField('00', 'br.gov.bcb.pix');
        
        // Subcampo 25: URL do PIX
        $pixUrl = $this->formatField('25', $location);
        
        return $gui . $pixUrl;
    }

    /**
     * Construir dados adicionais (Campo 62)
     */
    private function buildAdditionalData(string $transactionId = null): string
    {
        // Subcampo 05: Reference Label
        $reference = $transactionId ?: '***';
        return $this->formatField('05', $reference);
    }

    /**
     * Gerar location PIX (em produção, usar API do banco)
     */
    private function generatePixLocation(string $transactionId = null): string
    {
        // Em produção, esta URL viria da API do banco após criar a cobrança PIX
        $baseUrl = config('wifi.pix.base_url', 'pix.example.com.br');
        $uuid = $transactionId ?: $this->generateUUID();
        
        return "{$baseUrl}/qr/v2/{$uuid}";
    }

    /**
     * Limpar string removendo acentos e caracteres especiais
     */
    private function cleanString(string $text, int $maxLength = null): string
    {
        // Remover acentos
        $text = $this->removeAccents($text);
        
        // Manter apenas letras, números e espaços
        $text = preg_replace('/[^A-Za-z0-9\s]/', '', $text);
        
        // Limitar tamanho se especificado
        if ($maxLength) {
            $text = substr($text, 0, $maxLength);
        }
        
        return trim($text);
    }

    /**
     * Remover acentos de uma string
     */
    private function removeAccents(string $text): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A', 'Ä' => 'A',
            'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Õ' => 'O', 'Ô' => 'O', 'Ö' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
            'Ç' => 'C', 'Ñ' => 'N'
        ];

        return strtr($text, $map);
    }

    /**
     * Calcular CRC16-CCITT
     */
    private function calculateCRC16(string $data): string
    {
        $polynomial = 0x1021;
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= (ord($data[$i]) << 8);
            
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return sprintf('%04X', $crc);
    }

    /**
     * Gerar UUID simples
     */
    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Gerar URL para imagem QR Code
     */
    public function generateQRCodeImageUrl(string $emvString): string
    {
        // Usar API gratuita para gerar QR Code
        $size = '300x300';
        $encodedData = urlencode($emvString);
        
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}&data={$encodedData}";
    }

    /**
     * Validar string EMV
     */
    public function validateEMV(string $emvString): bool
    {
        try {
            // Verificar se tem tamanho mínimo
            if (strlen($emvString) < 50) {
                return false;
            }

            // Verificar se termina com campo CRC (63)
            if (substr($emvString, -8, 2) !== '63') {
                return false;
            }

            // Extrair CRC fornecido
            $providedCRC = substr($emvString, -4);
            
            // Construir string para validação: EMV sem CRC + placeholder "6304"
            $dataWithoutCRC = substr($emvString, 0, -8) . '6304';
            
            // Calcular CRC esperado
            $expectedCRC = $this->calculateCRC16($dataWithoutCRC);
            
            return strtoupper($providedCRC) === $expectedCRC;
            
        } catch (\Exception $e) {
            return false;
        }
    }
}
