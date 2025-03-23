<?php


declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Http\Contracts\RequestInterface;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;
use App\Infrastructure\Session\Contracts\SessionInterface;
use Exception;
use Random\RandomException;

#[Injectable]
#[Singleton]
class CsrfProtection implements CsrfProtectionInterface
{
    /**
     * Session-Schlüssel für CSRF-Tokens
     */
    protected const string TOKEN_SESSION_KEY = '_csrf_tokens';

    /**
     * Standard-Gültigkeitsdauer für Tokens in Sekunden
     */
    protected const int DEFAULT_TOKEN_LIFETIME = 7200; // 2 Stunden

    /**
     * Konstruktor
     */
    public function __construct(
        protected SessionInterface  $session,
        protected RequestInterface  $request,
        protected CsrfConfiguration $config
    )
    {
    }

    /**
     * {@inheritdoc}
     */
    public function generateToken(string $key = 'default', ?int $lifetime = null): string
    {
        // Verwende die konfigurierte Lebensdauer oder den Standardwert
        $lifetime = $lifetime ?? $this->config->defaultTokenLifetime ?? self::DEFAULT_TOKEN_LIFETIME;

        try {
            // Generiere ein zufälliges Token
            $token = bin2hex(random_bytes(32));
        } catch (RandomException) {
            // Fallback für den Fall, dass random_bytes() fehlschlägt
            $token = bin2hex(openssl_random_pseudo_bytes(32));
        } catch (Exception) {
            // Absoluter Fallback
            $token = md5(uniqid((string)mt_rand(), true));
        }

        // Speichere das Token mit Metadaten in der Session
        $tokens = $this->session->get(self::TOKEN_SESSION_KEY, []);
        $tokens[$key] = [
            'token' => $token,
            'created_at' => time(),
            'expires_at' => $lifetime > 0 ? time() + $lifetime : null
        ];

        $this->session->set(self::TOKEN_SESSION_KEY, $tokens);

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function validateToken(string $token, string $key = 'default', bool $removeAfterValidation = true): bool
    {
        $tokens = $this->session->get(self::TOKEN_SESSION_KEY, []);

        // Prüfe, ob ein Token für den angegebenen Schlüssel existiert
        if (!isset($tokens[$key])) {
            return false;
        }

        $tokenData = $tokens[$key];
        $storedToken = $tokenData['token'];

// Prüfe das Ablaufdatum, falls gesetzt
        if (isset($tokenData['expires_at'])) {
            if (time() > $tokenData['expires_at']) {
                // Token ist abgelaufen, entferne es
                if ($removeAfterValidation) {
                    unset($tokens[$key]);
                    $this->session->set(self::TOKEN_SESSION_KEY, $tokens);
                }
                return false;
            }
        }

        // Entferne das Token nach der Validierung, wenn gewünscht
        if ($removeAfterValidation) {
            unset($tokens[$key]);
            $this->session->set(self::TOKEN_SESSION_KEY, $tokens);
        }

        // Überprüfe das Token mit konstanter Zeit (verhindert Timing-Angriffe)
        return hash_equals($storedToken, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function tokenField(string $key = 'default', ?int $lifetime = null): string
    {
        $token = $this->generateToken($key, $lifetime);
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s" data-csrf-key="%s">',
            htmlspecialchars($token),
            htmlspecialchars($key)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateOrigin(string|array $allowedOrigins): bool
    {
        // Hole den Origin-Header oder falls nicht vorhanden den Referer
        $origin = $this->request->getHeader('origin');
        $referer = $origin ?? $this->request->getHeader('referer');

        if (!$referer) {
            // Wenn weder Origin noch Referer vorhanden sind
            return $this->config->requireOriginHeader;
        }

        // Konvertiere einzelne Origin zu Array
        $allowedOrigins = is_array($allowedOrigins) ? $allowedOrigins : [$allowedOrigins];

        // Ergänze mit den konfigurierten Standard-Origins
        if (!empty($this->config->trustedOrigins)) {
            $allowedOrigins = array_merge($allowedOrigins, $this->config->trustedOrigins);
        }

        // Überprüfe, ob der Referer eine der erlaubten Origins enthält
        return array_any($allowedOrigins, fn($allowed) => str_starts_with($referer, $allowed));

    }

    /**
     * {@inheritdoc}
     */
    public function shouldProtectRequest(): bool
    {
        // Schütze nur nicht-sichere Methoden (POST, PUT, DELETE, etc.)
        if ($this->request->isSecureMethod()) {
            return false;
        }

        // Prüfe, ob der aktuelle Pfad von der Konfiguration ausgeschlossen ist
        $path = $this->request->getPath();

        return !array_any($this->config->excludedPaths, fn($excludedPath) => str_starts_with($path, $excludedPath));

    }
}