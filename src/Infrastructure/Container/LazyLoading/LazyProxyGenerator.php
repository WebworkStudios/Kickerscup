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
            throw new RuntimeException("Kann keinen Proxy für Interface oder abstrakte Klasse erstellen: $className");
        }

        $proxyClassName = $this->getProxyClassName($className);
        $namespacePart = '';

        // Namespace-Code generieren, falls nötig
        $namespace = $reflector->getNamespaceName();
        if (!empty($namespace)) {
            $namespacePart = "namespace {$namespace}\\LazyProxies;\n\nuse {$className};\n";
        }

        // Methoden des Originals sammeln
        $methods = [];
        foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic()) {
                continue; // Konstruktor/Destruktor/Statische Methoden überspringen
            }

            $paramStr = $this->buildMethodParameters($method);
            $returnType = $method->hasReturnType() ? ': ' . $this->getReturnTypeString($method) : '';
            $methodName = $method->getName();

            $methods[] = <<<METHOD
    public function {$methodName}({$paramStr}){$returnType}
    {
        \$this->loadRealInstance();
        return \$this->realInstance->{$methodName}(...func_get_args());
    }
METHOD;
        }

        // Property-Zugriffe implementieren
        $properties = [];
        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $propertyName = $property->getName();
            $type = $property->hasType() ? $this->getPropertyTypeString($property) : '';

            $properties[] = <<<PROPERTY
    public {$type} \${$propertyName} {
        get {
            \$this->loadRealInstance();
            return \$this->realInstance->{$propertyName};
        }
        set (\$value) {
            \$this->loadRealInstance();
            \$this->realInstance->{$propertyName} = \$value;
        }
    }
PROPERTY;
        }

        // Klassen-Code zusammenstellen
        $proxyCode = <<<CODE
<?php

{$namespacePart}

use App\Infrastructure\Container\Contracts\ContainerInterface;

/**
 * Auto-generierter Lazy-Loading-Proxy für {$className}
 * @generated
 */
class {$proxyClassName}
{
    private ?object \$realInstance = null;
    private string \$className;
    private ContainerInterface \$container;

    public function __construct(ContainerInterface \$container, string \$className)
    {
        \$this->container = \$container;
        \$this->className = \$className;
    }

    protected function loadRealInstance(): void
    {
        if (\$this->realInstance === null) {
            \$this->realInstance = \$this->container->get(\$this->className);
        }
    }

{$this->indentCode(implode("\n\n", $properties), 4)}

{$this->indentCode(implode("\n\n", $methods), 4)}
}
CODE;

        // Klasse dynamisch definieren
        eval($proxyCode);

        // Code für Debugging-Zwecke speichern
        $this->proxyCache[$className] = $proxyCode;
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