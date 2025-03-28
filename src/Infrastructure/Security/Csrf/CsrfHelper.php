<?php


declare(strict_types=1);

namespace App\Infrastructure\Security\Csrf;

use App\Infrastructure\Container\Attributes\Injectable;
use App\Infrastructure\Container\Attributes\Singleton;
use App\Infrastructure\Security\Csrf\Contracts\CsrfProtectionInterface;

/**
 * Helper für CSRF-Token in Templates
 */
#[Injectable]
#[Singleton]
class CsrfHelper
{
    /**
     * Konstruktor
     */
    public function __construct(
        protected CsrfProtectionInterface $csrfProtection
    )
    {
    }

    /**
     * Gibt ein CSRF-Token-Feld zurück
     *
     * @param string $key Schlüssel zur Identifikation des Tokens
     * @return string HTML-Input-Feld
     */
    public function field(string $key = 'default'): string
    {
        return $this->csrfProtection->getTokenField($key);
    }

    /**
     * Gibt das CSRF-Token zurück
     *
     * @param string $key Schlüssel zur Identifikation des Tokens
     * @return string Das CSRF-Token
     */
    public function token(string $key = 'default'): string
    {
        return $this->csrfProtection->generateToken($key);
    }

    /**
     * Gibt ein Meta-Tag mit dem CSRF-Token zurück für JavaScript-Anwendungen
     *
     * @param string $key Schlüssel zur Identifikation des Tokens
     * @return string HTML-Meta-Tag
     */
    public function meta(string $key = 'default'): string
    {
        $token = $this->csrfProtection->generateToken($key);
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Gibt ein CSRF-Script zurück für automatische AJAX-Header-Setzung
     *
     * @param string $key Schlüssel zur Identifikation des Tokens
     * @return string JavaScript-Code
     */
    public function script(string $key = 'default'): string
    {
        $token = $this->csrfProtection->generateToken($key);

        return sprintf(
            '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    const token = "%s";
                    
                    // Setze CSRF-Token für AJAX-Requests
                    const oldXHROpen = XMLHttpRequest.prototype.open;
                    XMLHttpRequest.prototype.open = function() {
                        const result = oldXHROpen.apply(this, arguments);
                        this.setRequestHeader("X-CSRF-TOKEN", token);
                        return result;
                    };
                    
                    // Wenn Fetch API verwendet wird
                    const originalFetch = window.fetch;
                    window.fetch = function() {
                        const args = Array.from(arguments);
                        const resource = args[0];
                        const options = args[1] || {};
                        
                        if(typeof resource === "string" && resource.startsWith("/")) {
                            options.headers = options.headers || {};
                            options.headers["X-CSRF-TOKEN"] = token;
                            args[1] = options;
                        }
                        
                        return originalFetch.apply(window, args);
                    };
                });
            </script>',
            $token
        );
    }
}