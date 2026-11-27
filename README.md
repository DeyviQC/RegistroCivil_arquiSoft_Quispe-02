# Sistema Integral de Registro Civil

## Información del Proyecto
- **Sistema:** Sistema Integral de Registro Civil para la Municipalidad Distrital de Andrés Avelino Cáceres Dorregaray
- **Autor / Estudiante:** Quispe Chavez Deyvi Aderly
- **Curso:** Laboratorio de Arquitectura de Software (IS488)
- **Docente:** Lizbeth Jaico Quispe
- **Institución:** Universidad Nacional de San Cristóbal de Huamanga (UNSCH)
- **Caso de estudio:** Sistema de Gestión de Registro Civil

[![CI](https://github.com/DeyviQC/RegistroCivil_arquiSoft_Quispe-02/actions/workflows/ci.yml/badge.svg)](https://github.com/DeyviQC/RegistroCivil_arquiSoft_Quispe-02/actions/workflows/ci.yml)

> **Resultados del Sprint 2:** [sprint-2/resultados](sprint-2/resultados/): 7 de 8 atributos de calidad
> cumplidos, con gráficos de carga hasta 2 000 usuarios, OWASP ZAP, concurrencia y restauración.

## Estructura del repositorio

| Carpeta | Contenido | Sprint |
|---|---|---|
| `analisis-de-sistema/` | Actores, historias de usuario, requisitos, atributos y escenarios de calidad, restricciones, drivers y ADR | 1 |
| `arquitectura/` | Estilo (monolito modular por capas), enfoque (MVC con servicios), componentes | 1 |
| `diseño-de-software/` | Diseño interno, principios SOLID y patrones | 1 |
| `modelo-c4/` | Diagramas C4 (contexto, contenedores, componentes, código) | 1 |
| `codigo/` | Código fuente de la aplicación Laravel (primera versión funcional) | 2 |
| [`sprint-2/`](sprint-2/) | Entregables del Sprint 2: API REST y Swagger, especificaciones, análisis y evidencias de calidad ([índice](sprint-2/README.md) · [resultados](sprint-2/resultados/)) | 2 |
| `.github/workflows/` | Integración continua: pruebas en SQLite y MySQL con cobertura mínima del 80 %, PHPStan, auditoría de dependencias y la colección Postman con Newman | 2 |

## Ejecutar el código

Requisitos: PHP 8.4, Composer y MySQL (o SQLite para las pruebas).

```bash
cd codigo
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan test        # pruebas automatizadas
php artisan serve       # http://127.0.0.1:8000
```

Más detalle en [`codigo/README.md`](codigo/README.md) y [`codigo/DESARROLLO.md`](codigo/DESARROLLO.md).