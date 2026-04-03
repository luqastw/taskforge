# Plano de Implementação Feature por Feature
# TaskForge API

**Versão:** 1.0  
**Data:** 03/04/2026  
**Baseado em:** PRD v1.0 + SDD v1.0  
**Status:** Aguardando Aprovação do Cliente

---

## Como Funciona Este Plano

Este documento quebra o projeto em **features incrementais e independentes**. Cada feature:

1. Tem **critérios de aceitação claros**
2. É **testável individualmente**
3. Pode ser **aprovada antes de prosseguir**
4. Inclui **estimativa de tempo**
5. Lista **dependências** de outras features

**Fluxo de Aprovação:**
- Você revisa cada feature
- Aprova ou solicita mudanças
- Implemento apenas features aprovadas
- Demonstro a feature funcionando
- Passamos para a próxima

---

## Índice de Features

### Fase 1: Fundação
- [F001] Setup Docker e Infraestrutura Base
- [F002] Configuração Laravel e CI/CD
- [F003] Estrutura de Pastas e Arquitetura Base

### Fase 2: Autenticação e Multi-Tenancy
- [F004] Sistema de Multi-Tenancy (Tenant Model + Global Scope)
- [F005] Registro de Tenant
- [F006] Login e Logout (Laravel Sanctum)
- [F007] Convite de Membros
- [F008] Transferência de Ownership

### Fase 3: Autorização e Permissões
- [F009] Roles e Permissões (Spatie)
- [F010] Policies para Recursos
- [F011] Middleware de Tenant Resolution
- [F012] Listagem e Gestão de Membros

### Fase 4: Workspaces
- [F013] CRUD de Workspaces
- [F014] Associação de Membros a Workspaces
- [F015] Configurações de Workspace

### Fase 5: Projetos
- [F016] CRUD de Projetos
- [F017] Status e Estados de Projeto
- [F018] Colunas Kanban Customizáveis
- [F019] Membros de Projeto

### Fase 6: Tarefas Core
- [F020] CRUD de Tarefas
- [F021] Atribuição de Tarefas (Assignees)
- [F022] Prioridades e Deadlines
- [F023] Subtarefas (Relação Recursiva)

### Fase 7: Tarefas Avançadas
- [F024] Sistema de Tags
- [F025] Anexos de Arquivos (Spatie Media Library)
- [F026] Comentários em Tarefas
- [F027] Menções (@username) em Comentários
- [F028] Histórico de Alterações (Activity Log)

### Fase 8: Notificações
- [F029] Sistema de Notificações In-App
- [F030] Notificações por Email
- [F031] Jobs Agendados para Deadlines
- [F032] Preferências de Notificação

### Fase 9: Webhooks
- [F033] CRUD de Webhook Endpoints
- [F034] Sistema de Dispatch de Webhooks
- [F035] Assinatura HMAC de Payloads
- [F036] Retry com Backoff Exponencial
- [F037] Logs de Deliveries

### Fase 10: Relatórios e Analytics
- [F038] Relatório de Tarefas por Status
- [F039] Tarefas em Atraso
- [F040] Métricas de Produtividade
- [F041] Exportação CSV

### Fase 11: Qualidade e Documentação
- [F042] Documentação Swagger/OpenAPI
- [F043] Cobertura de Testes >= 80%
- [F044] Otimizações de Performance
- [F045] README e Documentação Final

### Fase 12: Deploy
- [F046] Pipeline CI/CD Completo
- [F047] Deploy em Ambiente de Staging
- [F048] Deploy em Produção
- [F049] Monitoramento e Alertas

---

## Detalhamento das Features

---

### **[F001] Setup Docker e Infraestrutura Base**

**Prioridade:** Crítica  
**Estimativa:** 4 horas  
**Dependências:** Nenhuma

#### Descrição
Configurar ambiente completo com Docker Compose incluindo PHP 8.3, MySQL 8.0, Redis e Nginx.

#### Entregáveis
- [ ] `docker-compose.yml` configurado
- [ ] Dockerfile para PHP 8.3 com extensões necessárias
- [ ] Nginx configurado como reverse proxy
- [ ] MySQL 8.0 rodando
- [ ] Redis Alpine rodando
- [ ] Volumes persistentes configurados
- [ ] Script de inicialização (`make setup` ou similar)

#### Critérios de Aceitação
- Comando `docker-compose up -d` sobe todos os serviços
- Aplicação acessível em `http://localhost:8000`
- MySQL acessível e criando database `taskforge`
- Redis respondendo a comandos
- Logs visíveis com `docker-compose logs`

#### Arquivos a Criar/Modificar
```
docker-compose.yml
docker/
├── php/
│   └── Dockerfile
├── nginx/
│   └── default.conf
└── mysql/
    └── init.sql (opcional)
Makefile (opcional para comandos úteis)
```

#### Comandos para Testar
```bash
docker-compose up -d
docker-compose ps  # Todos os serviços "Up"
docker-compose exec app php --version  # PHP 8.3
docker-compose exec db mysql -u root -p -e "SHOW DATABASES;"
docker-compose exec redis redis-cli ping  # PONG
```

---

### **[F002] Configuração Laravel e CI/CD**

**Prioridade:** Crítica  
**Estimativa:** 3 horas  
**Dependências:** F001

#### Descrição
Instalar Laravel 12, configurar .env, setup inicial e configurar GitHub Actions.

#### Entregáveis
- [ ] Laravel 12 instalado
- [ ] `.env.example` completo
- [ ] Database migrations rodando
- [ ] GitHub Actions workflow básico
- [ ] Pest PHP configurado
- [ ] PHP CS Fixer configurado

#### Critérios de Aceitação
- `php artisan --version` retorna Laravel 12.x
- `php artisan migrate` executa sem erros
- `php artisan test` executa testes (mesmo que vazios)
- GitHub Actions executa testes em cada push
- Code style check passa

#### Arquivos a Criar/Modificar
```
.env.example
.github/workflows/ci.yml
phpunit.xml → pest.xml
composer.json (adicionar deps)
.php-cs-fixer.php
```

#### Comandos para Testar
```bash
composer install
php artisan migrate:fresh
php artisan test
composer run lint
```

---

### **[F003] Estrutura de Pastas e Arquitetura Base**

**Prioridade:** Alta  
**Estimativa:** 2 horas  
**Dependências:** F002

#### Descrição
Criar estrutura de pastas seguindo a arquitetura definida no SDD.

#### Entregáveis
- [ ] Pastas criadas: Services, Repositories, Policies
- [ ] Interface base para Repositories
- [ ] Trait base para Tenant Scope
- [ ] Controller base com helpers
- [ ] Service Provider para bind de repositories

#### Critérios de Aceitação
- Estrutura de pastas criada e vazia
- Namespaces configurados no composer.json
- `composer dump-autoload` funciona
- Classes base acessíveis

#### Arquivos a Criar
```
app/
├── Services/
│   └── .gitkeep
├── Repositories/
│   ├── Contracts/
│   │   └── RepositoryInterface.php
│   └── BaseRepository.php
├── Traits/
│   └── BelongsToTenant.php
└── Providers/
    └── RepositoryServiceProvider.php
```

---

### **[F004] Sistema de Multi-Tenancy (Tenant Model + Global Scope)**

**Prioridade:** Crítica  
**Estimativa:** 6 horas  
**Dependências:** F003

#### Descrição
Implementar isolamento de dados por tenant_id com Global Scope automático.

#### Entregáveis
- [ ] Migration de `tenants` table
- [ ] Model `Tenant` com relacionamentos
- [ ] `TenantScope` Global Scope
- [ ] Trait `BelongsToTenant` para aplicar em models
- [ ] Testes de isolamento entre tenants

#### Critérios de Aceitação
- Tabela `tenants` criada
- Global Scope filtra automaticamente por tenant_id
- Criar registro automaticamente adiciona tenant_id
- Testes provam que Tenant A não vê dados do Tenant B
- Queries sempre incluem `WHERE tenant_id = ?`

#### Arquivos a Criar/Modificar
```
database/migrations/xxxx_create_tenants_table.php
app/Models/Tenant.php
app/Scopes/TenantScope.php
app/Traits/BelongsToTenant.php
tests/Unit/TenantScopeTest.php
tests/Feature/TenantIsolationTest.php
```

#### Testes Necessários
```php
test('tenant scope filters queries automatically')
test('creating model auto-assigns tenant_id')
test('tenant A cannot see tenant B data')
test('queries without tenant_id fail when user authenticated')
```

---

### **[F005] Registro de Tenant**

**Prioridade:** Crítica  
**Estimativa:** 4 horas  
**Dependências:** F004

#### Descrição
Endpoint para registro de novo tenant com criação automática do usuário owner.

#### Entregáveis
- [ ] Migration `users` table
- [ ] Model `User` com relacionamento a Tenant
- [ ] `POST /api/register` endpoint
- [ ] `RegisterRequest` com validações
- [ ] `AuthService` para lógica de negócio
- [ ] Testes de feature e unit

#### Critérios de Aceitação
- Endpoint aceita: company_name, name, email, password
- Cria tenant e usuário owner em transação
- Retorna token de autenticação
- Valida email único
- Senha hashada com bcrypt
- Testes cobrem casos de sucesso e erro

#### Arquivos a Criar/Modificar
```
database/migrations/xxxx_create_users_table.php
app/Models/User.php
app/Http/Controllers/AuthController.php
app/Http/Requests/RegisterRequest.php
app/Services/AuthService.php
routes/api.php
tests/Feature/Auth/RegisterTest.php
```

#### Request/Response
```
POST /api/register
{
  "company_name": "Acme Inc",
  "name": "John Doe",
  "email": "john@acme.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}

Response 201:
{
  "data": {
    "user": { ... },
    "tenant": { ... },
    "token": "1|xxxx"
  }
}
```

---

### **[F006] Login e Logout (Laravel Sanctum)**

**Prioridade:** Crítica  
**Estimativa:** 3 horas  
**Dependências:** F005

#### Descrição
Autenticação via Laravel Sanctum com geração e revogação de tokens.

#### Entregáveis
- [ ] Laravel Sanctum instalado e configurado
- [ ] `POST /api/login` endpoint
- [ ] `POST /api/logout` endpoint
- [ ] Rate limiting configurado (5 tentativas/min)
- [ ] Testes de autenticação

#### Critérios de Aceitação
- Login com email/password retorna token
- Token válido permite acesso a rotas protegidas
- Credenciais inválidas retornam 401
- Logout revoga token atual
- Rate limiting bloqueia após 5 tentativas

#### Arquivos a Criar/Modificar
```
config/sanctum.php
app/Http/Controllers/AuthController.php (add methods)
app/Http/Requests/LoginRequest.php
routes/api.php
tests/Feature/Auth/LoginTest.php
tests/Feature/Auth/LogoutTest.php
```

#### Request/Response
```
POST /api/login
{
  "email": "john@acme.com",
  "password": "secret123"
}

Response 200:
{
  "data": {
    "user": { ... },
    "token": "2|yyyy"
  }
}

POST /api/logout
Authorization: Bearer 2|yyyy

Response 204: (No Content)
```

---

### **[F007] Convite de Membros**

**Prioridade:** Alta  
**Estimativa:** 6 horas  
**Dependências:** F006

#### Descrição
Sistema de convite por email com token único e expiração.

#### Entregáveis
- [ ] Migration `invitations` table
- [ ] Model `Invitation`
- [ ] `POST /api/members/invite` endpoint
- [ ] `POST /api/invitations/accept` endpoint
- [ ] Email de convite
- [ ] Job assíncrono para envio de email
- [ ] Testes completos

#### Critérios de Aceitação
- Apenas owner/admin pode convidar
- Email enviado com link único
- Token expira em 7 dias
- Aceitar convite cria usuário e o associa ao tenant
- Convite já usado não pode ser reutilizado

#### Arquivos a Criar/Modificar
```
database/migrations/xxxx_create_invitations_table.php
app/Models/Invitation.php
app/Http/Controllers/MemberController.php
app/Http/Requests/InviteMemberRequest.php
app/Services/InvitationService.php
app/Mail/MemberInvitation.php
app/Jobs/SendInvitationEmail.php
tests/Feature/Members/InviteMemberTest.php
tests/Feature/Members/AcceptInvitationTest.php
```

---

### **[F008] Transferência de Ownership**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F009 (precisa de roles)

#### Descrição
Owner pode transferir propriedade do tenant para outro membro.

#### Entregáveis
- [ ] `POST /api/tenant/transfer-ownership` endpoint
- [ ] Validações (novo owner deve ser membro ativo)
- [ ] Owner antigo vira admin automaticamente
- [ ] Notificação para ambos
- [ ] Testes

#### Critérios de Aceitação
- Apenas owner atual pode transferir
- Novo owner recebe role "owner"
- Owner antigo vira "admin"
- Ambos recebem notificação
- Log de auditoria registrado

---

### **[F009] Roles e Permissões (Spatie)**

**Prioridade:** Crítica  
**Estimativa:** 5 horas  
**Dependências:** F006

#### Descrição
Implementar sistema de roles e permissões usando Spatie Laravel Permission.

#### Entregáveis
- [ ] Spatie Permission instalado
- [ ] Migration de roles e permissions
- [ ] Seeder com roles e permissões padrão
- [ ] Trait HasRoles aplicado em User
- [ ] Middleware de permissões configurado
- [ ] Testes

#### Critérios de Aceitação
- 4 roles criadas: owner, admin, member, viewer
- Permissões atribuídas corretamente
- `$user->can('create tasks')` funciona
- Middleware bloqueia acesso não autorizado
- Seeder populado

#### Arquivos a Criar/Modificar
```
config/permission.php
database/seeders/RolesAndPermissionsSeeder.php
app/Models/User.php (add HasRoles trait)
tests/Unit/PermissionsTest.php
```

#### Roles e Permissões
```
Owner: todas as permissões
Admin: todas exceto transferir ownership
Member: view/create/update tasks, view projects
Viewer: apenas view
```

---

### **[F010] Policies para Recursos**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F009

#### Descrição
Criar Laravel Policies para autorização declarativa por recurso.

#### Entregáveis
- [ ] WorkspacePolicy
- [ ] ProjectPolicy
- [ ] TaskPolicy
- [ ] Registrar policies no AuthServiceProvider
- [ ] Testes de autorização

#### Critérios de Aceitação
- Policy verifica tenant_id + permissões
- `$this->authorize('update', $task)` funciona
- Testes cobrem todos os métodos das policies
- Retorna 403 quando não autorizado

#### Arquivos a Criar
```
app/Policies/WorkspacePolicy.php
app/Policies/ProjectPolicy.php
app/Policies/TaskPolicy.php
app/Providers/AuthServiceProvider.php (register policies)
tests/Unit/Policies/TaskPolicyTest.php
```

---

### **[F011] Middleware de Tenant Resolution**

**Prioridade:** Alta  
**Estimativa:** 2 horas  
**Dependências:** F004

#### Descrição
Middleware que resolve o tenant do usuário autenticado e disponibiliza globalmente.

#### Entregáveis
- [ ] `ResolveTenant` middleware
- [ ] Registrar no kernel
- [ ] Aplicar em rotas autenticadas
- [ ] Testes

#### Critérios de Aceitação
- Tenant disponível via `app('tenant')` ou helper `tenant()`
- Retorna 404 se tenant não existir
- Apenas em rotas autenticadas

#### Arquivos a Criar/Modificar
```
app/Http/Middleware/ResolveTenant.php
app/Http/Kernel.php
app/helpers.php (helper tenant())
tests/Feature/Middleware/ResolveTenantTest.php
```

---

### **[F012] Listagem e Gestão de Membros**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F009, F010

#### Descrição
CRUD de membros do tenant com listagem, atualização de role e remoção.

#### Entregáveis
- [ ] `GET /api/members` (listagem paginada)
- [ ] `PATCH /api/members/{id}` (atualizar role)
- [ ] `DELETE /api/members/{id}` (remover membro)
- [ ] Reassign de tarefas ao remover
- [ ] Testes

#### Critérios de Aceitação
- Lista membros com filtros (role, status)
- Busca por nome/email
- Atualiza role validando permissões
- Remove membro e reassign tarefas
- Notifica membro removido

#### Arquivos a Criar/Modificar
```
app/Http/Controllers/MemberController.php (add methods)
app/Http/Requests/UpdateMemberRequest.php
app/Http/Resources/MemberResource.php
app/Services/MemberService.php
tests/Feature/Members/ListMembersTest.php
tests/Feature/Members/UpdateMemberTest.php
tests/Feature/Members/RemoveMemberTest.php
```

---

### **[F013] CRUD de Workspaces**

**Prioridade:** Alta  
**Estimativa:** 5 horas  
**Dependências:** F011, F010

#### Descrição
CRUD completo de workspaces com validações e autorização.

#### Entregáveis
- [ ] Migration `workspaces` table
- [ ] Model `Workspace`
- [ ] Endpoints: GET, POST, PUT, DELETE
- [ ] WorkspacePolicy
- [ ] WorkspaceService e Repository
- [ ] Testes

#### Critérios de Aceitação
- Listagem paginada com filtros
- Criar workspace validando nome único por tenant
- Atualizar workspace
- Soft delete de workspace
- Apenas owner/admin podem gerenciar

#### Arquivos a Criar
```
database/migrations/xxxx_create_workspaces_table.php
app/Models/Workspace.php
app/Http/Controllers/WorkspaceController.php
app/Http/Requests/StoreWorkspaceRequest.php
app/Http/Requests/UpdateWorkspaceRequest.php
app/Http/Resources/WorkspaceResource.php
app/Services/WorkspaceService.php
app/Repositories/WorkspaceRepository.php
app/Policies/WorkspacePolicy.php
routes/api.php
tests/Feature/Workspaces/WorkspaceCrudTest.php
```

---

### **[F014] Associação de Membros a Workspaces**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F013

#### Descrição
Associar membros específicos a workspaces.

#### Entregáveis
- [ ] Migration `workspace_user` pivot
- [ ] Endpoints para adicionar/remover membros
- [ ] `GET /api/workspaces/{id}/members`
- [ ] Testes

#### Critérios de Aceitação
- Adicionar múltiplos membros ao workspace
- Remover membro do workspace
- Listar membros do workspace
- Membro só vê projetos dos workspaces em que está

---

### **[F015] Configurações de Workspace**

**Prioridade:** Baixa  
**Estimativa:** 2 horas  
**Dependências:** F013

#### Descrição
Configurações customizáveis por workspace (timezone, formato de data, etc.)

#### Entregáveis
- [ ] Campo JSON `settings` na tabela workspaces
- [ ] Endpoint PATCH para atualizar settings
- [ ] Validação de settings
- [ ] Testes

---

### **[F016] CRUD de Projetos**

**Prioridade:** Alta  
**Estimativa:** 5 horas  
**Dependências:** F013

#### Descrição
CRUD completo de projetos dentro de workspaces.

#### Entregáveis
- [ ] Migration `projects` table
- [ ] Model `Project`
- [ ] Endpoints: GET, POST, PUT, DELETE
- [ ] ProjectPolicy
- [ ] ProjectService e Repository
- [ ] Testes

#### Critérios de Aceitação
- Criar projeto dentro de workspace
- Listar projetos com filtros (workspace, status)
- Atualizar projeto (nome, descrição, deadline)
- Soft delete
- Apenas membros do workspace veem projetos

#### Arquivos a Criar
```
database/migrations/xxxx_create_projects_table.php
app/Models/Project.php
app/Http/Controllers/ProjectController.php
app/Http/Requests/StoreProjectRequest.php
app/Http/Requests/UpdateProjectRequest.php
app/Http/Resources/ProjectResource.php
app/Services/ProjectService.php
app/Repositories/ProjectRepository.php
app/Policies/ProjectPolicy.php
routes/api.php
tests/Feature/Projects/ProjectCrudTest.php
```

---

### **[F017] Status e Estados de Projeto**

**Prioridade:** Média  
**Estimativa:** 2 horas  
**Dependências:** F016

#### Descrição
Gerenciamento de status do projeto (active, on_hold, archived).

#### Entregáveis
- [ ] Enum ou validação de status
- [ ] Endpoint PATCH para mudar status
- [ ] Event ProjectStatusChanged
- [ ] Testes

#### Critérios de Aceitação
- Validação de status válidos
- Transições registradas no activity log
- Webhook disparado ao arquivar

---

### **[F018] Colunas Kanban Customizáveis**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F016

#### Descrição
Colunas customizáveis para organização de tarefas estilo Kanban.

#### Entregáveis
- [ ] Migration `project_columns` table
- [ ] Model `ProjectColumn`
- [ ] CRUD de colunas
- [ ] Ordenação de colunas
- [ ] Colunas padrão ao criar projeto
- [ ] Testes

#### Critérios de Aceitação
- Projeto tem colunas padrão: Backlog, To Do, In Progress, Review, Done
- Admin pode criar/editar/deletar colunas
- Colunas ordenáveis
- Limite de tarefas por coluna (opcional)

---

### **[F019] Membros de Projeto**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F016

#### Descrição
Associar membros do workspace ao projeto.

#### Entregáveis
- [ ] Migration `project_user` pivot
- [ ] Endpoints para adicionar/remover membros
- [ ] Listar membros do projeto
- [ ] Testes

---

### **[F020] CRUD de Tarefas**

**Prioridade:** Crítica  
**Estimativa:** 6 horas  
**Dependências:** F018

#### Descrição
CRUD completo de tarefas com todos os campos principais.

#### Entregáveis
- [ ] Migration `tasks` table
- [ ] Model `Task`
- [ ] Endpoints: GET, POST, PUT, DELETE
- [ ] TaskPolicy
- [ ] TaskService e Repository
- [ ] Testes

#### Critérios de Aceitação
- Criar tarefa com título, descrição, prioridade, deadline
- Listar tarefas com filtros (projeto, coluna, prioridade)
- Atualizar tarefa
- Soft delete
- Validações em todos os campos

#### Arquivos a Criar
```
database/migrations/xxxx_create_tasks_table.php
app/Models/Task.php
app/Http/Controllers/TaskController.php
app/Http/Requests/StoreTaskRequest.php
app/Http/Requests/UpdateTaskRequest.php
app/Http/Resources/TaskResource.php
app/Services/TaskService.php
app/Repositories/TaskRepository.php
app/Policies/TaskPolicy.php
routes/api.php
tests/Feature/Tasks/TaskCrudTest.php
```

---

### **[F021] Atribuição de Tarefas (Assignees)**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F020

#### Descrição
Sistema de atribuição de tarefas a múltiplos membros.

#### Entregáveis
- [ ] Migration `task_user` pivot
- [ ] Relacionamento Many-to-Many
- [ ] Endpoint para atribuir/remover assignees
- [ ] Notificação ao atribuir
- [ ] Testes

#### Critérios de Aceitação
- Tarefa pode ter zero ou mais assignees
- Apenas membros do projeto podem ser atribuídos
- Notificação enviada ao atribuir
- Listar tarefas por assignee

---

### **[F022] Prioridades e Deadlines**

**Prioridade:** Média  
**Estimativa:** 2 horas  
**Dependências:** F020

#### Descrição
Sistema de prioridades e gestão de deadlines.

#### Entregáveis
- [ ] Enum de prioridades (low, medium, high, urgent)
- [ ] Campo deadline com validação
- [ ] Filtros por prioridade e deadline
- [ ] Ordenação por deadline
- [ ] Testes

---

### **[F023] Subtarefas (Relação Recursiva)**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F020

#### Descrição
Tarefas podem ter subtarefas em relação recursiva.

#### Entregáveis
- [ ] Campo `parent_id` em tasks
- [ ] Relacionamento self-referencing
- [ ] Endpoint para listar subtarefas
- [ ] Validação de profundidade (opcional)
- [ ] Testes

---

### **[F024] Sistema de Tags**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F020

#### Descrição
Tags customizáveis para categorização de tarefas.

#### Entregáveis
- [ ] Migration `tags` e `task_tag` tables
- [ ] Model `Tag`
- [ ] CRUD de tags
- [ ] Associar tags a tarefas
- [ ] Filtrar tarefas por tags
- [ ] Testes

---

### **[F025] Anexos de Arquivos (Spatie Media Library)**

**Prioridade:** Alta  
**Estimativa:** 5 horas  
**Dependências:** F020

#### Descrição
Upload e gestão de anexos em tarefas usando Spatie Media Library.

#### Entregáveis
- [ ] Spatie Media Library instalado
- [ ] Migration de media table
- [ ] Endpoint POST para upload
- [ ] Endpoint GET para listar anexos
- [ ] Endpoint DELETE para remover
- [ ] Validações (tipo, tamanho)
- [ ] Testes

#### Critérios de Aceitação
- Suporte a imagens, PDFs, docs
- Limite de 10MB por arquivo
- Preview para imagens
- Soft delete de anexos

---

### **[F026] Comentários em Tarefas**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F020

#### Descrição
Sistema de comentários com suporte a markdown.

#### Entregáveis
- [ ] Migration `comments` table
- [ ] Model `Comment`
- [ ] Endpoints: GET, POST, PUT, DELETE
- [ ] Suporte a markdown
- [ ] Testes

---

### **[F027] Menções (@username) em Comentários**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F026

#### Descrição
Sistema de menções para notificar membros em comentários.

#### Entregáveis
- [ ] Parser de menções (@username)
- [ ] Notificação ao ser mencionado
- [ ] Destacar menções no texto
- [ ] Testes

---

### **[F028] Histórico de Alterações (Activity Log)**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F020

#### Descrição
Log completo de alterações usando Spatie Activity Log.

#### Entregáveis
- [ ] Spatie Activity Log instalado
- [ ] Trait LogsActivity aplicado
- [ ] Endpoint GET para histórico
- [ ] Formatação de changes (before/after)
- [ ] Testes

---

### **[F029] Sistema de Notificações In-App**

**Prioridade:** Alta  
**Estimativa:** 5 horas  
**Dependências:** F021

#### Descrição
Notificações armazenadas em banco e exibidas no app.

#### Entregáveis
- [ ] Migration `notifications` table
- [ ] Notifications classes
- [ ] Endpoint GET para listar notificações
- [ ] Endpoint PATCH para marcar como lida
- [ ] Testes

---

### **[F030] Notificações por Email**

**Prioridade:** Média  
**Estimativa:** 4 horas  
**Dependências:** F029

#### Descrição
Envio de emails para eventos importantes.

#### Entregáveis
- [ ] Mailable classes
- [ ] Templates de email
- [ ] Jobs assíncronos para envio
- [ ] Configuração SMTP
- [ ] Testes

---

### **[F031] Jobs Agendados para Deadlines**

**Prioridade:** Alta  
**Estimativa:** 3 horas  
**Dependências:** F030

#### Descrição
Job diário para verificar deadlines e enviar notificações.

#### Entregáveis
- [ ] Job CheckUpcomingDeadlines
- [ ] Scheduler configurado
- [ ] Notificação 24h antes do deadline
- [ ] Testes

---

### **[F032] Preferências de Notificação**

**Prioridade:** Baixa  
**Estimativa:** 2 horas  
**Dependências:** F029

#### Descrição
Usuário configura quais notificações quer receber.

#### Entregáveis
- [ ] Campo JSON `notification_preferences` em users
- [ ] Endpoint para atualizar preferências
- [ ] Validação ao enviar notificações
- [ ] Testes

---

### **[F033] CRUD de Webhook Endpoints**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F011

#### Descrição
Gestão de endpoints de webhook cadastrados pelo tenant.

#### Entregáveis
- [ ] Migration `webhook_endpoints` table
- [ ] Model `WebhookEndpoint`
- [ ] CRUD endpoints
- [ ] Validação de URL
- [ ] Secret gerado automaticamente
- [ ] Testes

---

### **[F034] Sistema de Dispatch de Webhooks**

**Prioridade:** Alta  
**Estimativa:** 5 horas  
**Dependências:** F033

#### Descrição
Disparar webhooks em eventos específicos.

#### Entregáveis
- [ ] Event Listeners para eventos
- [ ] Job DispatchWebhook
- [ ] Queue workers configurados
- [ ] Testes

---

### **[F035] Assinatura HMAC de Payloads**

**Prioridade:** Alta  
**Estimativa:** 2 horas  
**Dependências:** F034

#### Descrição
Assinar payloads com HMAC-SHA256 para validação.

#### Entregáveis
- [ ] Geração de signature no dispatch
- [ ] Header X-Webhook-Signature
- [ ] Documentação de validação
- [ ] Testes

---

### **[F036] Retry com Backoff Exponencial**

**Prioridade:** Alta  
**Estimativa:** 3 horas  
**Dependências:** F034

#### Descrição
Sistema de retry automático com backoff exponencial.

#### Entregáveis
- [ ] Configuração de tries e backoff
- [ ] Logs de tentativas
- [ ] Desativação após múltiplas falhas
- [ ] Testes

---

### **[F037] Logs de Deliveries**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F034

#### Descrição
Registro completo de tentativas de entrega de webhooks.

#### Entregáveis
- [ ] Migration `webhook_deliveries` table
- [ ] Model `WebhookDelivery`
- [ ] Endpoint GET para consultar logs
- [ ] Reenvio manual de webhooks falhados
- [ ] Testes

---

### **[F038] Relatório de Tarefas por Status**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F020

#### Descrição
Relatório agregado de tarefas por status.

#### Entregáveis
- [ ] Endpoint GET com filtros
- [ ] Agrupamento por coluna/status
- [ ] Paginação
- [ ] Testes

---

### **[F039] Tarefas em Atraso**

**Prioridade:** Média  
**Estimativa:** 2 horas  
**Dependências:** F022

#### Descrição
Lista de tarefas com deadline vencido.

#### Entregáveis
- [ ] Endpoint GET
- [ ] Ordenação por dias de atraso
- [ ] Filtros por projeto/assignee
- [ ] Testes

---

### **[F040] Métricas de Produtividade**

**Prioridade:** Baixa  
**Estimativa:** 4 horas  
**Dependências:** F020

#### Descrição
Indicadores de performance da equipe.

#### Entregáveis
- [ ] Endpoint GET com métricas
- [ ] Tarefas completadas por período
- [ ] Tempo médio de conclusão
- [ ] Cache de resultados
- [ ] Testes

---

### **[F041] Exportação CSV**

**Prioridade:** Média  
**Estimativa:** 3 horas  
**Dependências:** F038

#### Descrição
Exportar relatórios em CSV.

#### Entregáveis
- [ ] Endpoint GET para export
- [ ] Job assíncrono para grandes volumes
- [ ] Headers em português
- [ ] Testes

---

### **[F042] Documentação Swagger/OpenAPI**

**Prioridade:** Alta  
**Estimativa:** 8 horas  
**Dependências:** Todas as features de API

#### Descrição
Documentação completa da API via L5-Swagger.

#### Entregáveis
- [ ] L5-Swagger instalado
- [ ] Annotations em todos os endpoints
- [ ] Schemas de requests/responses
- [ ] UI Swagger acessível
- [ ] Validação de spec OpenAPI

---

### **[F043] Cobertura de Testes >= 80%**

**Prioridade:** Crítica  
**Estimativa:** Contínua  
**Dependências:** Todas

#### Descrição
Garantir cobertura mínima de 80% em todo o código.

#### Entregáveis
- [ ] Feature tests para todos os endpoints
- [ ] Unit tests para Services
- [ ] Tests de Policies
- [ ] Comando de coverage configurado

---

### **[F044] Otimizações de Performance**

**Prioridade:** Média  
**Estimativa:** 6 horas  
**Dependências:** Todas

#### Descrição
Otimizar queries, cache e performance geral.

#### Entregáveis
- [ ] Eager loading configurado
- [ ] Índices otimizados
- [ ] Cache implementado
- [ ] Query optimization

---

### **[F045] README e Documentação Final**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** Todas

#### Descrição
Documentação completa do projeto.

#### Entregáveis
- [ ] README.md completo
- [ ] Instruções de setup
- [ ] Guia de contribuição
- [ ] Changelog

---

### **[F046] Pipeline CI/CD Completo**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F043

#### Descrição
Pipeline completo com lint, tests, build e deploy.

#### Entregáveis
- [ ] GitHub Actions workflow completo
- [ ] Lint automático
- [ ] Tests em cada push
- [ ] Build de imagem Docker
- [ ] Deploy automático

---

### **[F047] Deploy em Ambiente de Staging**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F046

#### Descrição
Deploy automático em ambiente de homologação.

---

### **[F048] Deploy em Produção**

**Prioridade:** Crítica  
**Estimativa:** 4 horas  
**Dependências:** F047

#### Descrição
Deploy em ambiente de produção.

---

### **[F049] Monitoramento e Alertas**

**Prioridade:** Alta  
**Estimativa:** 4 horas  
**Dependências:** F048

#### Descrição
Configurar monitoramento com Laravel Horizon e alertas.

---

## Resumo de Estimativas

| Fase | Features | Horas Estimadas |
|------|----------|-----------------|
| Fase 1: Fundação | 3 | 9h |
| Fase 2: Auth & Multi-Tenancy | 5 | 22h |
| Fase 3: Autorização | 4 | 15h |
| Fase 4: Workspaces | 3 | 10h |
| Fase 5: Projetos | 4 | 14h |
| Fase 6: Tarefas Core | 4 | 15h |
| Fase 7: Tarefas Avançadas | 5 | 17h |
| Fase 8: Notificações | 4 | 14h |
| Fase 9: Webhooks | 5 | 17h |
| Fase 10: Relatórios | 4 | 12h |
| Fase 11: Qualidade | 4 | 22h |
| Fase 12: Deploy | 4 | 16h |
| **TOTAL** | **49 Features** | **~183 horas** |

---

## Como Proceder

**Opção 1: Aprovação em Lote por Fase**
- Aprove uma fase inteira
- Implemento todas as features da fase
- Demonstro funcionando
- Passamos para a próxima fase

**Opção 2: Aprovação Feature por Feature**
- Aprove feature específica (ex: F001)
- Implemento apenas ela
- Demonstro funcionando
- Você aprova e vamos para a próxima

**Opção 3: Customizada**
- Escolha features específicas de fases diferentes
- Defina prioridades customizadas

---

## Próximos Passos

1. **Você revisa este plano**
2. **Decide qual abordagem prefere (Fase, Feature ou Custom)**
3. **Aprova a primeira feature/fase**
4. **Eu implemento**
5. **Demonstro funcionando**
6. **Repetimos até conclusão**

**Qual feature ou fase você gostaria que eu começasse?**
