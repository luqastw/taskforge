# Software Design Document (SDD)
# TaskForge API

**Versão:** 1.0  
**Data:** 03/04/2026  
**Baseado em:** PRD TaskForge API v1.0  
**Status:** Draft - Aguardando Aprovação

---

## 1. Visão Geral da Arquitetura

### 1.1 Estilo Arquitetural
O TaskForge API utiliza uma arquitetura **Monólito Modular em Camadas** com princípios de separação de responsabilidades (SoC) e inversão de dependências (DIP).

### 1.2 Princípios Arquiteturais
- **Single Responsibility Principle (SRP):** Cada classe tem uma única razão para mudar
- **Dependency Inversion Principle (DIP):** Dependências apontam para abstrações
- **Repository Pattern:** Abstração da camada de dados
- **Service Layer:** Lógica de negócio isolada dos controllers
- **Policy-Based Authorization:** Autorização declarativa via Laravel Policies
- **Event-Driven Side Effects:** Webhooks e notificações via Events/Listeners

---

## 2. Diagrama de Arquitetura de Alto Nível

```
┌─────────────────────────────────────────────────────────────┐
│                        CLIENT (Frontend)                     │
│              (Web App, Mobile App, Integrações)              │
└──────────────────────┬──────────────────────────────────────┘
                       │ HTTPS/JSON
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                      NGINX (Reverse Proxy)                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL APPLICATION                       │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              Middleware Layer                          │ │
│  │  • Authentication (Sanctum)                            │ │
│  │  • Tenant Resolution                                   │ │
│  │  • Rate Limiting                                       │ │
│  │  • CORS                                                │ │
│  └────────────┬───────────────────────────────────────────┘ │
│               ▼                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              Controller Layer                          │ │
│  │  • Request Validation (Form Requests)                  │ │
│  │  • Authorization (Policies)                            │ │
│  │  • Delegation to Services                              │ │
│  │  • Response Formatting (API Resources)                 │ │
│  └────────────┬───────────────────────────────────────────┘ │
│               ▼                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              Service Layer                             │ │
│  │  • Business Logic                                      │ │
│  │  • Transaction Management                              │ │
│  │  • Event Dispatching                                   │ │
│  │  • Job Dispatching                                     │ │
│  └────────────┬───────────────────────────────────────────┘ │
│               ▼                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              Repository Layer                          │ │
│  │  • Data Access Abstraction                             │ │
│  │  • Query Building                                      │ │
│  │  • Caching Logic                                       │ │
│  └────────────┬───────────────────────────────────────────┘ │
│               ▼                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │              Model Layer (Eloquent)                    │ │
│  │  • Data Representation                                 │ │
│  │  • Relationships                                       │ │
│  │  • Global Scopes (Tenant Isolation)                    │ │
│  └────────────┬───────────────────────────────────────────┘ │
└───────────────┼──────────────────────────────────────────────┘
                │
    ┌───────────┴───────────┬──────────────┬─────────────┐
    ▼                       ▼              ▼             ▼
┌─────────┐         ┌──────────┐    ┌──────────┐  ┌──────────┐
│  MySQL  │         │  Redis   │    │  Queue   │  │  Storage │
│ (Dados) │         │ (Cache)  │    │ Workers  │  │  (S3)    │
└─────────┘         └──────────┘    └──────────┘  └──────────┘
```

---

## 3. Estrutura de Camadas Detalhada

### 3.1 Camada de Apresentação (Controllers)

**Responsabilidade:** Receber requisições HTTP, validar entrada, autorizar ação e retornar resposta formatada.

**Componentes:**
- **Controllers:** Um controller por recurso principal
- **Form Requests:** Validação e autorização de inputs
- **API Resources:** Transformação de dados para JSON

**Exemplo de Controller:**
```php
class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $this->authorize('create', Task::class);
        
        $task = $this->taskService->createTask(
            $request->validated()
        );
        
        return TaskResource::make($task)
            ->response()
            ->setStatusCode(201);
    }
}
```

**Princípios:**
- Controller não contém lógica de negócio
- Delega tudo para Services
- Usa Type Hints para injeção de dependências
- Retorna sempre API Resources

---

### 3.2 Camada de Validação (Form Requests)

**Responsabilidade:** Validar dados de entrada e autorizar acesso preliminar.

**Exemplo:**
```php
class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => ['required', 'exists:projects,id'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['exists:users,id'],
        ];
    }
}
```

---

### 3.3 Camada de Negócio (Services)

**Responsabilidade:** Implementar regras de negócio, orquestrar repositórios, disparar eventos e jobs.

**Princípios:**
- Uma Service por domínio (TaskService, ProjectService, etc.)
- Métodos públicos representam casos de uso
- Usa transações para operações complexas
- Dispara eventos para side effects

**Exemplo:**
```php
class TaskService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private UserRepository $userRepository
    ) {}

    public function createTask(array $data): Task
    {
        return DB::transaction(function () use ($data) {
            $task = $this->taskRepository->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'project_id' => $data['project_id'],
                'priority' => $data['priority'],
                'deadline' => $data['deadline'] ?? null,
                'tenant_id' => auth()->user()->tenant_id,
            ]);

            if (isset($data['assignee_ids'])) {
                $task->assignees()->attach($data['assignee_ids']);
            }

            event(new TaskCreated($task));

            return $task->load('assignees', 'project');
        });
    }

    public function assignTask(Task $task, array $userIds): Task
    {
        $task->assignees()->sync($userIds);
        
        event(new TaskAssigned($task, $userIds));
        
        return $task->load('assignees');
    }
}
```

---

### 3.4 Camada de Dados (Repositories)

**Responsabilidade:** Abstrair acesso ao banco de dados, queries complexas e cache.

**Princípios:**
- Interface + Implementação para facilitar testes
- Métodos descritivos (findByProject, findOverdue, etc.)
- Cache quando apropriado
- Eager loading para evitar N+1

**Exemplo:**
```php
interface TaskRepositoryInterface
{
    public function create(array $data): Task;
    public function findById(int $id): ?Task;
    public function findByProject(int $projectId): Collection;
    public function findOverdue(): Collection;
    public function update(Task $task, array $data): Task;
    public function delete(Task $task): bool;
}

class TaskRepository implements TaskRepositoryInterface
{
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function findById(int $id): ?Task
    {
        return Cache::remember(
            "task.{$id}",
            now()->addMinutes(10),
            fn () => Task::with(['assignees', 'project', 'tags'])->find($id)
        );
    }

    public function findByProject(int $projectId): Collection
    {
        return Task::where('project_id', $projectId)
            ->with(['assignees', 'tags'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findOverdue(): Collection
    {
        return Task::where('deadline', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->with(['assignees', 'project'])
            ->get();
    }
}
```

---

### 3.5 Camada de Modelo (Eloquent Models)

**Responsabilidade:** Representar entidades do domínio, relacionamentos e regras de dados.

**Princípios:**
- Models magros (lógica mínima)
- Global Scopes para tenant isolation
- Casts para type safety
- Relacionamentos bem definidos

**Exemplo:**
```php
class Task extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'project_id',
        'column_id',
        'parent_id',
        'priority',
        'deadline',
        'tenant_id',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    // Relationships
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')
            ->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

---

## 4. Multi-Tenancy: Implementação Detalhada

### 4.1 Estratégia: Shared Database com Tenant ID

Todos os tenants compartilham o mesmo banco de dados, mas cada registro possui um campo `tenant_id` que identifica a qual tenant pertence.

### 4.2 Global Scope para Isolamento Automático

```php
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $builder->where("{$model->getTable()}.tenant_id", auth()->user()->tenant_id);
        }
    }
}
```

Aplicado automaticamente em todos os models que pertencem a tenants:
```php
protected static function booted(): void
{
    static::addGlobalScope(new TenantScope);
    
    static::creating(function ($model) {
        if (auth()->check() && !$model->tenant_id) {
            $model->tenant_id = auth()->user()->tenant_id;
        }
    });
}
```

### 4.3 Middleware de Resolução de Tenant

```php
class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $tenant = Tenant::find($request->user()->tenant_id);
            
            if (!$tenant) {
                return response()->json([
                    'message' => 'Tenant not found'
                ], 404);
            }
            
            app()->instance('tenant', $tenant);
        }
        
        return $next($request);
    }
}
```

### 4.4 Testes de Isolamento

Cada feature test deve verificar isolamento:
```php
test('users cannot see tasks from other tenants', function () {
    $tenant1 = Tenant::factory()->create();
    $tenant2 = Tenant::factory()->create();
    
    $user1 = User::factory()->for($tenant1)->create();
    $user2 = User::factory()->for($tenant2)->create();
    
    $task1 = Task::factory()->for($tenant1)->create();
    $task2 = Task::factory()->for($tenant2)->create();
    
    actingAs($user1)
        ->getJson('/api/tasks')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $task1->id);
});
```

---

## 5. Sistema de Autenticação e Autorização

### 5.1 Autenticação com Laravel Sanctum

**Fluxo:**
1. Usuário envia credenciais para `/api/login`
2. Sistema valida credenciais
3. Gera token via `$user->createToken('api')`
4. Retorna token para o cliente
5. Cliente inclui token em todas as requisições: `Authorization: Bearer {token}`

**Implementação:**
```php
class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => UserResource::make($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
}
```

### 5.2 Autorização com Policies

Cada Model principal tem uma Policy correspondente:

```php
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view tasks');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('create tasks');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id 
            && $user->can('update tasks');
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id 
            && $user->can('delete tasks');
    }

    public function assign(User $user, Task $task): bool
    {
        // Apenas membros do projeto podem atribuir
        return $task->project->members()->where('user_id', $user->id)->exists();
    }
}
```

### 5.3 Roles e Permissões com Spatie

**Roles:**
- `owner` - Todas as permissões
- `admin` - Gerenciamento exceto billing
- `member` - CRUD de tarefas nos projetos atribuídos
- `viewer` - Apenas leitura

**Permissões:**
```php
// Workspaces
'view workspaces', 'create workspaces', 'update workspaces', 'delete workspaces'

// Projects
'view projects', 'create projects', 'update projects', 'delete projects', 'archive projects'

// Tasks
'view tasks', 'create tasks', 'update tasks', 'delete tasks', 'assign tasks'

// Members
'view members', 'invite members', 'update members', 'remove members'

// Webhooks
'view webhooks', 'create webhooks', 'update webhooks', 'delete webhooks'
```

**Seeder:**
```php
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $owner = Role::create(['name' => 'owner']);
        $admin = Role::create(['name' => 'admin']);
        $member = Role::create(['name' => 'member']);
        $viewer = Role::create(['name' => 'viewer']);

        // Owner tem todas
        $owner->givePermissionTo(Permission::all());

        // Admin tem todas exceto gestão de billing/owner
        $admin->givePermissionTo([
            'view workspaces', 'create workspaces', 'update workspaces', 'delete workspaces',
            'view projects', 'create projects', 'update projects', 'delete projects',
            'view tasks', 'create tasks', 'update tasks', 'delete tasks',
            'view members', 'invite members', 'remove members',
        ]);

        // Member tem permissões básicas
        $member->givePermissionTo([
            'view workspaces', 'view projects', 
            'view tasks', 'create tasks', 'update tasks',
        ]);

        // Viewer só visualiza
        $viewer->givePermissionTo([
            'view workspaces', 'view projects', 'view tasks', 'view members',
        ]);
    }
}
```

---

## 6. Sistema de Filas e Jobs Assíncronos

### 6.1 Configuração

**Driver:** Redis  
**Supervisor:** Laravel Horizon

### 6.2 Filas Disponíveis

- `default` - Jobs gerais
- `notifications` - Envio de emails e notificações
- `webhooks` - Disparos de webhooks
- `reports` - Geração de relatórios

### 6.3 Jobs Principais

**SendTaskAssignedNotification:**
```php
class SendTaskAssignedNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Task $task,
        public User $assignee
    ) {}

    public function handle(): void
    {
        $this->assignee->notify(new TaskAssignedNotification($this->task));
    }
}
```

**DispatchWebhook:**
```php
class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $payload
    ) {}

    public function handle(): void
    {
        $signature = hash_hmac('sha256', json_encode($this->payload), $this->endpoint->secret);

        $response = Http::withHeaders([
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Event' => $this->event,
        ])->post($this->endpoint->url, $this->payload);

        WebhookDelivery::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => $this->payload,
            'response_status' => $response->status(),
            'response_body' => $response->body(),
            'delivered_at' => now(),
        ]);

        if ($response->failed()) {
            throw new \Exception('Webhook delivery failed');
        }
    }
}
```

**CheckUpcomingDeadlines:**
```php
class CheckUpcomingDeadlines implements ShouldQueue
{
    public function handle(): void
    {
        $tasks = Task::where('deadline', '>=', now())
            ->where('deadline', '<=', now()->addDay())
            ->whereNull('completed_at')
            ->with('assignees')
            ->get();

        foreach ($tasks as $task) {
            foreach ($task->assignees as $assignee) {
                $assignee->notify(new DeadlineApproachingNotification($task));
            }
        }
    }
}
```

### 6.4 Agendamento (Scheduler)

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->job(new CheckUpcomingDeadlines)
        ->dailyAt('09:00')
        ->timezone('America/Sao_Paulo');

    $schedule->job(new SendDigestEmail)
        ->weeklyOn(1, '08:00'); // Segunda-feira 8h

    $schedule->command('horizon:snapshot')->everyFiveMinutes();
}
```

---

## 7. Sistema de Webhooks

### 7.1 Arquitetura

```
Event Occurs → Event Listener → DispatchWebhook Job → HTTP Request → External System
                                        ↓
                                 WebhookDelivery Log
                                        ↓
                                  Retry on Failure
```

### 7.2 Event Listener

```php
class NotifyWebhookSubscribers
{
    public function handle(TaskCreated $event): void
    {
        $endpoints = WebhookEndpoint::where('tenant_id', $event->task->tenant_id)
            ->where('active', true)
            ->whereJsonContains('events', 'task.created')
            ->get();

        foreach ($endpoints as $endpoint) {
            DispatchWebhook::dispatch(
                $endpoint,
                'task.created',
                [
                    'event' => 'task.created',
                    'timestamp' => now()->toIso8601String(),
                    'data' => TaskResource::make($event->task)->resolve(),
                ]
            );
        }
    }
}
```

### 7.3 Payload Signature

Cliente valida assinatura:
```php
$signature = hash_hmac('sha256', $requestBody, $webhookSecret);

if (!hash_equals($signature, $request->header('X-Webhook-Signature'))) {
    abort(401, 'Invalid signature');
}
```

---

## 8. Modelo de Dados Detalhado

### 8.1 Schema de Tabelas Principais

**tenants**
```sql
CREATE TABLE tenants (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_slug (slug)
);
```

**users**
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_email_per_tenant (email, tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id)
);
```

**workspaces**
```sql
CREATE TABLE workspaces (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    settings JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id)
);
```

**projects**
```sql
CREATE TABLE projects (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'on_hold', 'archived') DEFAULT 'active',
    deadline DATE NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_workspace (workspace_id),
    INDEX idx_status (status)
);
```

**project_columns**
```sql
CREATE TABLE project_columns (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    order INT NOT NULL DEFAULT 0,
    color VARCHAR(7),
    task_limit INT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_project (project_id),
    INDEX idx_order (project_id, order)
);
```

**tasks**
```sql
CREATE TABLE tasks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    project_id BIGINT UNSIGNED NOT NULL,
    column_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    deadline DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (column_id) REFERENCES project_columns(id),
    FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_project (project_id),
    INDEX idx_column (column_id),
    INDEX idx_deadline (deadline),
    INDEX idx_parent (parent_id)
);
```

**task_user (pivot)**
```sql
CREATE TABLE task_user (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (task_id, user_id),
    INDEX idx_user (user_id)
);
```

**comments**
```sql
CREATE TABLE comments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    task_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    tenant_id BIGINT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_task (task_id),
    INDEX idx_tenant (tenant_id)
);
```

**tags**
```sql
CREATE TABLE tags (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    color VARCHAR(7),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tag_per_tenant (name, tenant_id),
    INDEX idx_tenant (tenant_id)
);
```

**task_tag (pivot)**
```sql
CREATE TABLE task_tag (
    task_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (task_id, tag_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);
```

**webhook_endpoints**
```sql
CREATE TABLE webhook_endpoints (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    tenant_id BIGINT UNSIGNED NOT NULL,
    url VARCHAR(255) NOT NULL,
    secret VARCHAR(255) NOT NULL,
    events JSON NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant (tenant_id),
    INDEX idx_active (active)
);
```

**webhook_deliveries**
```sql
CREATE TABLE webhook_deliveries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    webhook_endpoint_id BIGINT UNSIGNED NOT NULL,
    event VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    response_status INT,
    response_body TEXT,
    delivered_at TIMESTAMP,
    created_at TIMESTAMP,
    FOREIGN KEY (webhook_endpoint_id) REFERENCES webhook_endpoints(id) ON DELETE CASCADE,
    INDEX idx_endpoint (webhook_endpoint_id),
    INDEX idx_event (event),
    INDEX idx_delivered (delivered_at)
);
```

**notifications**
```sql
CREATE TABLE notifications (
    id CHAR(36) PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id BIGINT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    INDEX idx_notifiable (notifiable_type, notifiable_id),
    INDEX idx_read (read_at)
);
```

---

## 9. API Response Format Padrão

### 9.1 Success Response

**Single Resource:**
```json
{
  "data": {
    "id": 1,
    "title": "Implementar autenticação",
    "description": "...",
    "priority": "high",
    "deadline": "2026-04-10T23:59:59.000000Z",
    "project": {
      "id": 5,
      "name": "TaskForge API"
    },
    "assignees": [
      {
        "id": 10,
        "name": "João Silva",
        "email": "joao@example.com"
      }
    ],
    "created_at": "2026-04-03T10:30:00.000000Z",
    "updated_at": "2026-04-03T15:45:00.000000Z"
  }
}
```

**Collection:**
```json
{
  "data": [
    { /* resource 1 */ },
    { /* resource 2 */ }
  ],
  "links": {
    "first": "http://api.example.com/tasks?page=1",
    "last": "http://api.example.com/tasks?page=10",
    "prev": null,
    "next": "http://api.example.com/tasks?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

### 9.2 Error Response

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": [
      "The title field is required."
    ],
    "deadline": [
      "The deadline must be a date after today."
    ]
  }
}
```

**HTTP Status Codes:**
- `200 OK` - Sucesso
- `201 Created` - Recurso criado
- `204 No Content` - Sucesso sem body (ex: DELETE)
- `400 Bad Request` - Erro de validação
- `401 Unauthorized` - Não autenticado
- `403 Forbidden` - Não autorizado
- `404 Not Found` - Recurso não encontrado
- `422 Unprocessable Entity` - Validação falhou
- `429 Too Many Requests` - Rate limit excedido
- `500 Internal Server Error` - Erro no servidor

---

## 10. Estratégia de Cache

### 10.1 Camadas de Cache

**1. Query Cache (Redis):**
```php
Cache::remember("task.{$id}", 600, function () use ($id) {
    return Task::with(['assignees', 'project'])->find($id);
});
```

**2. API Response Cache:**
```php
// Middleware CacheResponse
Route::middleware('cache.response:600')->get('/projects', [ProjectController::class, 'index']);
```

**3. Invalidação:**
```php
// Observers
class TaskObserver
{
    public function updated(Task $task): void
    {
        Cache::forget("task.{$task->id}");
        Cache::tags(['project:' . $task->project_id])->flush();
    }
}
```

### 10.2 Cache Tags Strategy

```php
// Cachear por tenant
Cache::tags(["tenant:{$tenantId}", 'tasks'])->put($key, $value, $ttl);

// Invalidar todas as tasks do tenant
Cache::tags("tenant:{$tenantId}")->flush();

// Invalidar apenas tasks
Cache::tags('tasks')->flush();
```

---

## 11. Segurança

### 11.1 Proteções Implementadas

**SQL Injection:** Eloquent ORM com prepared statements  
**XSS:** Sanitização automática de outputs via Blade (API não retorna HTML)  
**CSRF:** Não aplicável em API stateless  
**Mass Assignment:** `$fillable` em todos os models  
**Rate Limiting:** Middleware throttle em todas as rotas  
**CORS:** Configuração restrita de origens permitidas

### 11.2 Rate Limiting

```php
// config/sanctum.php
'middleware' => [
    'throttle:api', // 60 requests/min por padrão
],

// Rotas específicas
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});
```

### 11.3 Input Validation

Todas as entradas validadas via Form Requests com regras estritas.

### 11.4 Sensitive Data

```env
# Nunca commitado
DB_PASSWORD=
REDIS_PASSWORD=
APP_KEY=
SANCTUM_SECRET=
WEBHOOK_SECRET=
```

---

## 12. Estratégia de Testes

### 12.1 Pirâmide de Testes

```
        /\
       /  \  E2E (10%)
      /----\
     / Unit \ (40%)
    /--------\
   /  Feature \ (50%)
  /------------\
```

### 12.2 Feature Tests

Testam endpoints completos:
```php
test('authenticated user can create task', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user->tenant)->create();

    actingAs($user)
        ->postJson('/api/tasks', [
            'title' => 'Nova tarefa',
            'project_id' => $project->id,
            'priority' => 'high',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Nova tarefa');

    assertDatabaseHas('tasks', [
        'title' => 'Nova tarefa',
        'tenant_id' => $user->tenant_id,
    ]);
});
```

### 12.3 Unit Tests

Testam Services isoladamente:
```php
test('TaskService creates task with assignees', function () {
    $tenant = Tenant::factory()->create();
    $project = Project::factory()->for($tenant)->create();
    $users = User::factory()->count(2)->for($tenant)->create();

    $service = app(TaskService::class);
    
    $task = $service->createTask([
        'title' => 'Test task',
        'project_id' => $project->id,
        'assignee_ids' => $users->pluck('id')->toArray(),
    ]);

    expect($task->assignees)->toHaveCount(2);
});
```

### 12.4 Cobertura

**Meta:** >= 80%  
**Comando:** `php artisan test --coverage --min=80`

---

## 13. CI/CD Pipeline

### 13.1 GitHub Actions Workflow

```yaml
name: CI/CD Pipeline

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: PHP CS Fixer
        run: composer run lint

  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
      redis:
        image: redis:alpine
    steps:
      - uses: actions/checkout@v3
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: php artisan test --coverage --min=80

  build:
    needs: [lint, test]
    runs-on: ubuntu-latest
    steps:
      - name: Build Docker image
        run: docker build -t taskforge-api .

  deploy-staging:
    if: github.ref == 'refs/heads/develop'
    needs: build
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to staging
        run: # deploy script

  deploy-production:
    if: github.ref == 'refs/heads/main'
    needs: build
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to production
        run: # deploy script
```

---

## 14. Considerações de Performance

### 14.1 Otimizações de Query

- Eager Loading para evitar N+1
- Índices em colunas frequentemente consultadas
- Paginação obrigatória em listas
- Select específico de colunas quando possível

### 14.2 Cache Strategy

- Cache de queries frequentes (10min TTL)
- Cache de responses de API (5min TTL)
- Invalidação inteligente via observers

### 14.3 Queue Processing

- Jobs pesados sempre assíncronos
- Múltiplos workers por fila
- Horizon para monitoramento

---

## 15. Monitoramento e Observabilidade

### 15.1 Logs

**Laravel Log:**
```php
Log::channel('stack')->info('Task created', [
    'task_id' => $task->id,
    'user_id' => auth()->id(),
]);
```

**Canais:**
- `daily` - Logs gerais
- `slack` - Erros críticos
- `stderr` - Produção (Docker)

### 15.2 Métricas (Laravel Horizon)

- Jobs processados
- Jobs falhados
- Tempo de processamento
- Throughput

### 15.3 Health Checks

```php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'redis' => Redis::ping() ? 'connected' : 'disconnected',
    ]);
});
```

---

## 16. Plano de Rollout

### Fase 1: Setup (Semana 1)
- Configuração Docker
- Laravel 12 instalado
- CI/CD básico
- Banco de dados estruturado

### Fase 2: Auth & Tenants (Semana 2)
- Registro e login
- Multi-tenancy
- Sanctum configurado
- Testes de isolamento

### Fase 3: Core Features (Semanas 3-4)
- Workspaces, Projects, Tasks
- Permissões com Spatie
- Policies implementadas

### Fase 4: Advanced Features (Semanas 5-6)
- Comentários e anexos
- Notificações
- Webhooks

### Fase 5: Analytics & Polish (Semana 7)
- Relatórios
- Documentação Swagger
- Otimizações

### Fase 6: Deploy (Semana 8)
- Testes finais
- Deploy em produção
- Monitoramento

---

## 17. Aprovações

| Papel | Nome | Data | Assinatura |
|-------|------|------|------------|
| Tech Lead | A definir | - | - |
| Senior Developer | A definir | - | - |
| DevOps | A definir | - | - |

---

**Próximos Passos:**
1. Revisão e aprovação deste SDD
2. Criação do backlog detalhado por feature
3. Configuração do ambiente de desenvolvimento
4. Início do Sprint 1: Setup & Infraestrutura
