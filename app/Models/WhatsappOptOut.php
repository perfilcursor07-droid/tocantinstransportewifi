<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registro de descadastro (opt-out) do WhatsApp por telefone.
 *
 * Usado para filtrar passageiros que pediram para parar de receber
 * mensagens não-transacionais (avaliação, lembretes de marketing).
 */
class WhatsappOptOut extends Model
{
    protected $fillable = [
        'phone',
        'phone_last8',
        'source',
        'keyword',
        'opted_out_at',
    ];

    protected $casts = [
        'opted_out_at' => 'datetime',
    ];

    /**
     * Palavras-chave que disparam o descadastro automático.
     * Comparadas após normalizar a mensagem (sem acento, minúsculas, sem pontuação).
     */
    public const OPT_OUT_KEYWORDS = [
        'parar', 'pare', 'sair', 'cancelar', 'descadastrar',
        'remover', 'stop', 'nao quero', 'não quero', 'me tira',
    ];

    /**
     * Normaliza um telefone para somente dígitos.
     */
    public static function normalize(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    /**
     * Retorna os últimos 8 dígitos (casamento robusto entre formatos).
     */
    public static function last8(?string $phone): string
    {
        return substr(self::normalize($phone), -8);
    }

    /**
     * Verifica se o texto recebido é um pedido de descadastro.
     *
     * Para evitar falso-positivo (ex.: "tive que sair correndo do ônibus" numa
     * avaliação), só considera opt-out quando a mensagem é exatamente a palavra
     * ou bem curta (até 3 palavras) contendo a palavra isolada.
     */
    public static function isOptOutMessage(string $message): bool
    {
        $normalized = self::normalizeText($message);

        if ($normalized === '') {
            return false;
        }

        $wordCount = count(explode(' ', $normalized));

        foreach (self::OPT_OUT_KEYWORDS as $keyword) {
            $kw = self::normalizeText($keyword);

            // Mensagem exatamente a palavra/expressão.
            if ($normalized === $kw) {
                return true;
            }

            // Mensagem curta (até 3 palavras) que contém a palavra isolada.
            if ($wordCount <= 3 && preg_match('/\b' . preg_quote($kw, '/') . '\b/u', $normalized)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se um telefone está descadastrado (compara pelos últimos 8 dígitos).
     */
    public static function isOptedOut(?string $phone): bool
    {
        $last8 = self::last8($phone);

        if ($last8 === '') {
            return false;
        }

        return self::query()->where('phone_last8', $last8)->exists();
    }

    /**
     * Registra (ou mantém) o descadastro de um telefone.
     */
    public static function optOut(?string $phone, string $source = 'keyword', ?string $keyword = null): ?self
    {
        $normalized = self::normalize($phone);

        if ($normalized === '') {
            return null;
        }

        return self::updateOrCreate(
            ['phone' => $normalized],
            [
                'phone_last8' => self::last8($normalized),
                'source' => $source,
                'keyword' => $keyword,
                'opted_out_at' => now(),
            ]
        );
    }

    /**
     * Remove o descadastro (re-opt-in), caso o passageiro volte a interagir.
     */
    public static function optIn(?string $phone): void
    {
        $last8 = self::last8($phone);

        if ($last8 === '') {
            return;
        }

        self::query()->where('phone_last8', $last8)->delete();
    }

    /**
     * Normaliza texto: minúsculas, sem acento, espaços colapsados.
     */
    protected static function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $map = [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }
}
