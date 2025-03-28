<?php

declare(strict_types=1);

namespace App\Infrastructure\Container\LazyLoading;

use App\Infrastructure\Container\Contracts\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Generator für Lazy-Loading-Proxies
 */
class LazyProxyGenerator
{
    /**
     * Cache für generierte Proxy-Klassen
     *
     * @var array<string, string>
     */
    protected array $proxyCache = [];

    /**
     * Konstruktor
     */
    public function __construct(
        protected ContainerInterface $container
    ) {
    }

    /**
     * Erstellt einen Lazy-Loading-Proxy für eine Klasse
     *
     * @param string $className Der Name der originalen Klasse
     * @return object Der erstellte Proxy
     */
    public function createProxy(string $className): object
    {
        // Zuerst den Cache prüfen
        $proxyClassName = $this->getProxyClassName($className);

        if (!class_exists($proxyClassName, false)) {
            // Proxy-Klasse nicht im Cache, generiere sie
            $this->generateProxyClass($className);
        }

        // Proxy erstellen und zurückgeben
        return new $proxyClassName($this->container, $className);
    }

    /**
     * Generiert den Namen der Proxy-Klasse
     */
    protected function getProxyClassName(string $className): string
    {
        return 'LazyProxy_' . md5($className);
    }

    /**
     * Generiert und registriert eine Proxy-Klasse
     */
    protected function generateProxyClass(string $className): void
    {
        $reflector = new ReflectionClass($className);

        if ($reflector->isInterface() || $reflector->isAbstract()) {
            throw new RuntimeException(
                "Kann keinen Proxy für Interface oder abstrakte Klasse erstellen: $className. " .
                "Verwenden Sie eine konkrete Implementierung."
            );
        }

        $proxyClassName = $this->getProxyClassName($className);
        $namespace = $reflector->getNamespaceName();

        // Generiere den Code ohne PHP-Tags, um eval() einfacher zu machen
        $code = "";

        // Namespace und Imports
        if (!empty($namespace)) {
            $code .= "namespace {$namespace}\\LazyProxies;\n\n";
            $code .= "use {$className};\n";
        }

        $code .= "use App\\Infrastructure\\Container\\Contracts\\ContainerInterface;\n\n";

        // Klassendefinition beginnen
        $code .= "class {$proxyClassName} {\n";

        // Properties
        $code .= "    private ?\$realInstance = null;\n";
        $code .= "    private string \$className;\n";
        $code .= "    private ContainerInterface \$container;\n\n";

        // Konstruktor
        $code .= "    public function __construct(ContainerInterface \$container, string \$className) {\n";
        $code .= "        \$this->container = \$container;\n";
        $code .= "        \$this->className = \$className;\n";
        $code .= "    }\n\n";

        // loadRealInstance Methode
        $code .= "    protected function loadRealInstance(): void {\n";
        $code .= "        if (\$this->realInstance === null) {\n";
        $code .= "            \$this->realInstance = \$this->container->get(\$this->className);\n";
        $code .= "        }\n";
        $code .= "    }\n\n";

        // Public Properties mit __get und __set Methoden
        $publicProperties = [];
        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $publicProperties[] = $propertyName;

            $code .= "    private \${$propertyName};\n";
        }

        if (!empty($publicProperties)) {
            // __get Methode
            $code .= "\n    public function __get(string \$name) {\n";
            $code .= "        \$this->loadRealInstance();\n";
            $code .= "        return \$this->realInstance->{\$name};\n";
            $code .= "    }\n\n";

            // __set Methode
            $code .= "    public function __set(string \$name, mixed \$value): void {\n";
            $code .= "        \$this->loadRealInstance();\n";
            $code .= "        \$this->realInstance->{\$name} = \$value;\n";
            $code .= "    }\n\n";
        }

        // Methoden
        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue;
            }

            $methodName = $method->getName();
            $paramStr = $this->buildMethodParameters($method);
            $returnType = $method->hasReturnType() ? ': ' . $this->getReturnTypeString($method) : '';

            $code .= "    public function {$methodName}({$paramStr}){$returnType} {\n";
            $code .= "        \$this->loadRealInstance();\n";
            $code .= "        return \$this->realInstance->{$methodName}(...func_get_args());\n";
            $code .= "    }\n\n";
        }

        // Klasse abschließen
        $code .= "}\n";

        // Jetzt den kompletten Code mit Namespace-Block für eval vorbereiten
        $fullCode = "<?php\n";
        if (!empty($namespace)) {
            $fullCode .= $code;
        } else {
            $fullCode .= $code;
        }

        // Code for debugging
        $this->proxyCache[$className] = $fullCode;
        file_put_contents('generated_proxy_debug.php', $fullCode);
        // Eval the code
        eval($fullCode);
    }

    /**
     * Baut den Parameter-String für eine Methode
     */
    protected function buildMethodParameters(ReflectionMethod $method): string
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramStr = '';

            // Typ hinzufügen
            if ($param->hasType()) {
                $paramStr .= $this->getParameterTypeString($param) . ' ';
            }

            // Referenz-Parameter
            if ($param->isPassedByReference()) {
                $paramStr .= '&';
            }

            // Variadic-Parameter
            if ($param->isVariadic()) {
                $paramStr .= '...';
            }

            $paramStr .= '$' . $param->getName();

            // Default-Wert
            if ($param->isDefaultValueAvailable()) {
                $default = $param->getDefaultValue();
                $paramStr .= ' = ' . $this->formatDefaultValue($default);
            }

            $params[] = $paramStr;
        }

        return implode(', ', $params);
    }

    /**
     * Formatiert einen Default-Wert für die Verwendung im Code
     */
    protected function formatDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_string($value)) {
            return "'" . addslashes($value) . "'";
        } elseif (is_array($value)) {
            if (empty($value)) {
                return '[]';
            }
            $items = [];
            foreach ($value as $k => $v) {
                $items[] = is_int($k) ? $this->formatDefaultValue($v) :
                    $this->formatDefaultValue($k) . ' => ' . $this->formatDefaultValue($v);
            }
            return '[' . implode(', ', $items) . ']';
        }

        return (string)$value;
    }

    /**
     * Gibt den Typ-String für einen Parameter zurück
     */
    protected function getParameterTypeString(\ReflectionParameter $param): string
    {
        $type = $param->getType();
        if ($type === null) {
            return '';
        }

        if ($type instanceof \ReflectionNamedType) {
            // Vermeidung von ?mixed
            if ($type->getName() === 'mixed') {
                return 'mixed';
            }
            return ($type->allowsNull() ? '?' : '') . $type->getName();
        } elseif ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $types[] = $unionType->getName();
            }
            return implode('|', $types);
        } elseif ($type instanceof \ReflectionIntersectionType) {
            $types = [];
            foreach ($type->getTypes() as $intersectionType) {
                $types[] = $intersectionType->getName();
            }
            return implode('&', $types);
        }

        return '';
    }

    /**
     * Gibt den Return-Typ für eine Methode zurück
     */
    protected function getReturnTypeString(ReflectionMethod $method): string
    {
        $returnType = $method->getReturnType();
        if ($returnType === null) {
            return '';
        }

        if ($returnType instanceof \ReflectionNamedType) {
            // Hier ist der Fix: Wenn der Typ 'mixed' ist, brauchen wir kein '?'
            if ($returnType->getName() === 'mixed') {
                return 'mixed';
            }
            return ($returnType->allowsNull() ? '?' : '') . $returnType->getName();
        } elseif ($returnType instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($returnType->getTypes() as $unionType) {
                $types[] = $unionType->getName();
            }
            return implode('|', $types);
        } elseif ($returnType instanceof \ReflectionIntersectionType) {
            $types = [];
            foreach ($returnType->getTypes() as $intersectionType) {
                $types[] = $intersectionType->getName();
            }
            return implode('&', $types);
        }

        return '';
    }


    /**
     * Gibt den Typ-String für eine Property zurück
     */
    protected function getPropertyTypeString(\ReflectionProperty $property): string
    {
        $type = $property->getType();
        if ($type === null) {
            return '';
        }

        if ($type instanceof \ReflectionNamedType) {
            // Vermeidung von ?mixed
            if ($type->getName() === 'mixed') {
                return 'mixed';
            }
            return ($type->allowsNull() ? '?' : '') . $type->getName();
        } elseif ($type instanceof \ReflectionUnionType) {
            $types = [];
            foreach ($type->getTypes() as $unionType) {
                $types[] = $unionType->getName();
            }
            return implode('|', $types);
        } elseif ($type instanceof \ReflectionIntersectionType) {
            $types = [];
            foreach ($type->getTypes() as $intersectionType) {
                $types[] = $intersectionType->getName();
            }
            return implode('&', $types);
        }

        return '';
    }

    /**
     * Fügt Einrückungen zu einem Code-Block hinzu
     */
    protected function indentCode(string $code, int $spaces): string
    {
        $lines = explode("\n", $code);
        $indent = str_repeat(' ', $spaces);

        foreach ($lines as &$line) {
            if (!empty(trim($line))) {
                $line = $indent . $line;
            }
        }

        return implode("\n", $lines);
    }
}