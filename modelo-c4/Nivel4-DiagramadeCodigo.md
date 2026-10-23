# Nivel 4 · Diagrama de código

> Proyecto: Registro Civil · Módulo detallado: Actas y PDF · Modelo: C4 · Nivel 4 de 4 · Notación: UML · Versión: 1.0.

## 1. Objetivo

Detallar las clases y colaboraciones del registro de un acta existente con PDF. Se describe el desarrollo actual, sin inventar clases para la integración Izipay pendiente.

| Aspecto | Descripción |
|---|---|
| Pregunta que responde | ¿Qué clases intervienen en el registro y cómo colaboran? |
| Audiencia | Desarrolladores. |
| Notación | Clases y secuencia UML expresadas en Mermaid. |
| Por qué este módulo | Reúne validación, permisos, almacenamiento, transacción, numeración y auditoría. |
| Nivel anterior | [Componentes](Nivel3-Diagrama-de-Componentes.md). |

## 2. Diagrama de clases

```mermaid
classDiagram
    class ActaController {
        +store(StoreActaRequest request)
        +index(Request request)
    }
    class StoreActaRequest {
        +rules() array
    }
    class ActaPolicy {
        +create(User user) bool
        +update(User user, Acta acta) bool
        +delete(User user, Acta acta) bool
    }
    class ActaService {
        +registrar(array datosActa, array datosDetalle, UploadedFile pdf, User usuario) Acta
        +buscar(array filtros)
    }
    class PdfValidationService {
        +validar(UploadedFile archivo) array
        +calcularHash(UploadedFile archivo) string
        +existeDuplicadoPorHash(string hash) bool
        +existeDuplicadoPorNumero(string numeroActa, int anio, string tipo) bool
    }
    class PdfStorageService {
        +guardar(UploadedFile archivo, string tipo, int anio, string numeroActa) array
        +eliminar(string ruta) void
    }
    class AuditoriaService {
        +registrar()
    }
    class Acta {
        +numero_acta
        +tipo
        +anio
        +pdf_ruta
        +pdf_hash
        +nacimiento() HasOne
        +matrimonio() HasOne
        +defuncion() HasOne
    }
    class ActaNacimiento
    class ActaMatrimonio
    class ActaDefuncion
    ActaController ..> StoreActaRequest : entrada
    ActaController ..> ActaPolicy : autoriza mediante Laravel
    ActaController --> ActaService : inyectado
    ActaService --> PdfValidationService : inyectado
    ActaService --> PdfStorageService : inyectado
    ActaService --> AuditoriaService : inyectado
    ActaService ..> Acta : persiste
    PdfValidationService ..> Acta : consulta duplicados
    Acta "1" --> "0..1" ActaNacimiento : nacimiento
    Acta "1" --> "0..1" ActaMatrimonio : matrimonio
    Acta "1" --> "0..1" ActaDefuncion : defuncion
```

Se muestran miembros relevantes, no todas las firmas ni propiedades. Solo el detalle correspondiente al tipo se crea en el registro normal. Las relaciones expresan asociación Eloquent, sin atribuir composición o interfaces inexistentes.

### 2.1 Responsabilidades MVC con servicios

| Capa | Contiene | Regla |
|---|---|---|
| Presentación | Blade y Bootstrap. | Mostrar formularios y resultados. |
| Control | Controlador, validaciones y autorización. | Coordinar la petición y aplicar controles de entrada y acceso. |
| Servicios | Registro, validación PDF, almacenamiento y auditoría. | Coordinar operaciones; dependencia de Laravel explícita. |
| Modelo / persistencia | Eloquent y datos relacionales. | Conservar registros y relaciones en MySQL. |

### 2.2 Clases del módulo Actas

| Clase | Tipo | Capa | Responsabilidad |
|---|---|---|---|
| ActaController | Controlador | Control | Recibir y coordinar registro y consulta. |
| StoreActaRequest y Requests por tipo | Validación | Control | Validar reglas generales y específicas. |
| ActaPolicy | Política | Autorización | Controlar operaciones del personal. |
| ActaService | Servicio | Servicios | Coordinar duplicados, PDF, transacción y detalle. |
| Acta | Modelo | Persistencia | Datos centrales y numeración con bloqueo de secuencia. |
| ActaNacimiento, ActaMatrimonio, ActaDefuncion | Modelos | Persistencia | Datos específicos del tipo de acta. |

### 2.3 Clases de apoyo documental

| Clase | Tipo | Capa | Responsabilidad |
|---|---|---|---|
| PdfValidationService | Servicio | Servicios | Extensión, MIME, tamaño, cabecera y hash. |
| PdfStorageService | Servicio | Servicios | Nombre físico, guardado y compensación del archivo. |
| AuditoriaService | Servicio | Servicios | Registrar la operación. |

### 2.4 Notación UML utilizada

| Símbolo | Significado | Ejemplo |
|---|---|---|
| Flecha continua | Asociación o colaborador. | ActaService → PdfStorageService. |
| Flecha discontinua | Uso o dependencia. | PdfValidationService → Acta. |
| Multiplicidad | Cantidad de registros relacionados. | Acta → cero o un detalle de cada clase. |
| `+` | Miembro público mostrado. | `registrar`. |

## 3. Diagrama de secuencia: registrar acta

```mermaid
sequenceDiagram
    actor Personal
    participant HTTP as Laravel / validación / políticas
    participant C as ActaController
    participant S as ActaService
    participant V as PdfValidationService
    participant F as PdfStorageService
    participant D as Eloquent / MySQL
    participant A as AuditoriaService
    Personal->>HTTP: POST /actas con datos y PDF
    HTTP->>C: Entrada general validada y sesión del personal
    C->>HTTP: Autorizar creación y validar detalle del tipo
    C->>S: registrar(datosActa, datosDetalle, pdf, usuario)
    S->>V: existeDuplicadoPorNumero(numero, anio, tipo)
    V->>D: Consultar coincidencia
    D-->>V: Resultado
    V-->>S: Duplicado o disponible
    alt Duplicado por número
        S->>A: Registrar intento de duplicado
        S-->>C: Error de validación
    else Número disponible
        S->>V: validar(pdf), calcularHash(pdf), existeDuplicadoPorHash(hash)
        V->>D: Consultar hash
        D-->>V: Resultado
        V-->>S: Validación y duplicado
        alt PDF inválido o hash duplicado
            Note over S,A: El hash duplicado registra intento de duplicado
            S-->>C: Error de validación
        else PDF aceptado
            S->>F: guardar(pdf, tipo, anio, numero)
            F-->>S: Ruta y metadatos del archivo
            S->>D: Iniciar transacción
            S->>D: Guardar Acta y detalle
            Note over D: Acta bloquea secuencia y asigna numeración
            S->>A: Registrar CREAR_ACTA
            A->>D: Guardar auditoría
            alt Persistencia correcta
                D-->>S: Commit y acta persistida
                S->>D: Recargar acta y relaciones
                D-->>S: Acta completa
                S-->>C: Acta registrada
            else Error de persistencia
                D-->>S: Rollback
                S->>F: eliminar(ruta) como compensación
                S-->>C: Propagar error
            end
        end
    end
    Note over C,Personal: En éxito se redirige a ficha.<br/>Los errores de validación vuelven al formulario.
```

| Paso | Origen → destino | Acción |
|---|---|---|
| 1 | Personal → Laravel | Enviar datos y PDF mediante ruta protegida. |
| 2 | Controlador → validación / política | Validar datos específicos y autorizar creación. |
| 3 | Servicio → validación PDF | Rechazar número repetido, PDF inválido o hash duplicado. |
| 4 | Servicio → Storage | Guardar PDF con nombre generado. |
| 5 | Servicio → Eloquent / MySQL | Guardar acta, numeración, detalle y auditoría en transacción. |
| 6 | Alternativa de fallo | Revertir datos e intentar eliminar el archivo ya guardado. |
| 7 | Controlador → navegador | Redirigir a la ficha si el registro termina correctamente. |

El guardado del archivo precede a la transacción; su eliminación es compensación, no atomicidad entre MySQL y sistema de archivos. La cabecera `%PDF-` es un control de formato, no una validación criptográfica o análisis completo del documento. El hash sirve para integridad y duplicados, no sustituye una firma.

## 4. Patrones y principios que se ven en este nivel

| Elemento del diagrama | Patrón o principio |
|---|---|
| Vista, controlador y modelos | MVC. |
| ActaService y servicios PDF | Service Layer y separación de responsabilidades. |
| Modelos Eloquent | Active Record. |
| Colaboradores por constructor | Inyección de dependencias concretas. |
| Validación, almacenamiento y auditoría separados | Responsabilidad única aplicada parcialmente. |

La edición ordinaria y eliminación del acta están denegadas por política. Filiación es una operación distinta con campos limitados, versión e historial. El flujo aquí representado no firma, entrega ni procesa pagos. Véanse los [patrones](../diseño-de-software/diseño-interno/patrones-de-diseño.md) y [principios](../diseño-de-software/diseño-interno/principios-de-diseño.md).
