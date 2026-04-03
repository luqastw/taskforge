# TaskForge API

[![CI/CD](https://github.com/username/taskforge-api/workflows/CI%2FCD%20Pipeline/badge.svg)](https://github.com/username/taskforge-api/actions)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red)](https://laravel.com)

API RESTful SaaS multi-tenant para gestão de tarefas e projetos.

## Stack Tecnológica

- **Laravel 12** - Framework PHP moderno
- **PHP 8.3** - Linguagem de programação
- **MySQL 8.0** - Banco de dados relacional
- **Redis** - Cache e filas
- **Docker** - Containerização
- **Pest PHP** - Framework de testes
- **Laravel Sanctum** - Autenticação API
- **Spatie Permission** - Roles e permissões
- **Spatie Activity Log** - Histórico de alterações

## Requisitos

- Docker & Docker Compose
- Make (opcional, para comandos facilitados)

## Setup Rápido

### 1. Clone o repositório

```bash
git clone https://github.com/username/taskforge-api.git
cd taskforge-api
```

### 2. Usando Makefile (recomendado)

```bash
make setup
```

Isso irá:
- Criar arquivo `.env` a partir do `.env.example`
- Buildar as imagens Docker
- Iniciar os containers
- Instalar dependências Composer
- Gerar chave da aplicação
- Criar symbolic link para storage
- Configurar permissões

### 3. Ou manualmente:

```bash
# Copiar .env
cp .env.example .env

# Iniciar containers
docker-compose up -d

# Instalar dependências
docker-compose exec app composer install

# Gerar chave
docker-compose exec app php artisan key:generate

# Rodar migrations
docker-compose exec app php artisan migrate

# Configurar permissões
docker-compose exec app chmod -R 777 storage bootstrap/cache
```

## Comandos Úteis

Com Makefile:

```bash
make help              # Mostra todos os comandos disponíveis
make up                # Inicia os containers
make down              # Para os containers
make restart           # Reinicia os containers
make logs              # Mostra logs
make shell             # Acessa shell do container
make test              # Executa testes
make test-coverage     # Testes com cobertura
make lint              # Verifica estilo de código
make lint-fix          # Corrige estilo de código
make migrate           # Executa migrations
make fresh             # Reseta banco, migra e popula
```

Sem Makefile:

```bash
docker-compose up -d                              # Iniciar
docker-compose exec app php artisan test          # Testes
docker-compose exec app ./vendor/bin/pint         # Lint
docker-compose exec app php artisan migrate       # Migrate
```

## Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/     # Controllers da API
│   ├── Middleware/      # Middlewares customizados
│   ├── Requests/        # Form Requests (validação)
│   └── Resources/       # API Resources (transformação)
├── Models/              # Eloquent Models
├── Services/            # Regras de negócio
├── Repositories/        # Abstração de dados
│   └── Contracts/       # Interfaces
├── Policies/            # Autorização
├── Events/              # Eventos do sistema
├── Listeners/           # Ouvintes de eventos
├── Jobs/                # Jobs assíncronos
├── Notifications/       # Notificações
├── Scopes/              # Global Scopes (Multi-tenancy)
└── Traits/              # Traits reutilizáveis
```

## Arquitetura

O projeto segue uma **arquitetura em camadas**:

1. **Controller** - Recebe requisições HTTP
2. **Form Request** - Valida dados de entrada
3. **Service** - Lógica de negócio
4. **Repository** - Acesso a dados
5. **Model** - Representação de entidades
6. **API Resource** - Formatação de resposta

### Multi-Tenancy

Isolamento de dados por `tenant_id` usando:
- **Global Scope** - Filtragem automática
- **Trait BelongsToTenant** - Aplicada em todos os models de tenant
- **Middleware ResolveTenant** - Resolve tenant da requisição

## Testes

Usamos **Pest PHP** para testes:

```bash
# Rodar todos os testes
make test

# Com cobertura (mínimo 80%)
make test-coverage

# Apenas testes de feature
php artisan test --testsuite=Feature

# Apenas testes unitários
php artisan test --testsuite=Unit
```

## Code Style

Seguimos PSR-12 com Laravel Pint:

```bash
# Verificar
make lint

# Corrigir automaticamente
make lint-fix
```

## Documentação

- [PRD - Product Requirements Document](docs/PRD.md)
- [SDD - Software Design Document](docs/SDD.md)
- [Plano de Implementação](docs/IMPLEMENTATION_PLAN.md)

## API Endpoints

A documentação completa da API estará disponível via Swagger/OpenAPI em:

```
http://localhost:8000/api/documentation
```

(Será implementado nas próximas fases)

## Variáveis de Ambiente

Principais variáveis (veja `.env.example` completo):

```env
APP_NAME=TaskForge
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=taskforge
DB_USERNAME=taskforge
DB_PASSWORD=taskforge

REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

## CI/CD

Pipeline automatizado com GitHub Actions:

- ✅ **Lint** - Verificação de código
- ✅ **Tests** - Execução de testes
- ✅ **Build** - Build da imagem Docker
- ✅ **Deploy** - Deploy automático (staging/production)

## Contribuindo

1. Crie um branch a partir de `develop`
2. Use Conventional Commits (`feat:`, `fix:`, `chore:`)
3. Garanta 80%+ de cobertura de testes
4. Rode `make lint-fix` antes de commitar
5. Abra um Pull Request

## Git Flow

- `main` - Produção
- `develop` - Staging
- `feature/*` - Novas features
- `fix/*` - Correções
- `chore/*` - Manutenção

## Licença

MIT

## Suporte

Para dúvidas ou problemas, abra uma issue no GitHub.

---

**Status do Projeto:** 🚀 Em Desenvolvimento Ativo  
**Fase Atual:** Fase 1 - Fundação ✅ Concluída
