# Product Requirements Document (PRD)
# TaskForge API

**Versão:** 1.0  
**Data:** 03/04/2026  
**Autor:** Time TaskForge  
**Status:** Draft - Aguardando Aprovação

---

## 1. Visão Geral do Produto

### 1.1 Resumo Executivo
O TaskForge API é uma plataforma SaaS multi-tenant de gestão de tarefas e projetos, fornecendo uma API RESTful completa para empresas gerenciarem seus projetos, equipes e fluxos de trabalho de forma isolada e segura.

### 1.2 Problema
Empresas precisam de uma solução robusta e escalável para:
- Gerenciar múltiplos projetos e tarefas simultaneamente
- Organizar equipes com permissões granulares
- Acompanhar progresso e produtividade
- Integrar com sistemas externos via webhooks
- Manter histórico completo de alterações
- Garantir isolamento total de dados entre organizações

### 1.3 Solução
API RESTful moderna construída com Laravel 12 que oferece:
- Multi-tenancy com isolamento completo de dados
- Sistema de autenticação e autorização robusto
- Gestão de workspaces, projetos e tarefas
- Notificações em tempo real
- Sistema de webhooks para integrações
- Relatórios e métricas de produtividade

### 1.4 Público-Alvo
- Empresas de software que precisam de sistema de gestão de tarefas
- Desenvolvedores que precisam integrar gestão de tarefas em suas aplicações
- Equipes que trabalham com metodologias ágeis (Kanban, Scrum)

---

## 2. Objetivos e Métricas de Sucesso

### 2.1 Objetivos de Negócio
- Oferecer plataforma SaaS escalável para múltiplos clientes
- Demonstrar excelência técnica em arquitetura Laravel
- Criar portfólio técnico de alto nível

### 2.2 Objetivos Técnicos
- Cobertura de testes >= 80%
- Tempo de resposta de API < 200ms (p95)
- Documentação completa via OpenAPI/Swagger
- Pipeline CI/CD automatizado
- Ambiente reproduzível via Docker

### 2.3 Métricas de Sucesso
- Taxa de erro de API < 1%
- Uptime >= 99.5%
- Performance de queries < 100ms
- Todos os endpoints documentados
- Deploy automatizado funcionando

---

## 3. Requisitos Funcionais

### 3.1 Autenticação e Tenants (FR-001)

#### FR-001.1: Registro de Tenant
**Prioridade:** Alta  
**Descrição:** Permitir que novas empresas se registrem na plataforma

**Critérios de Aceitação:**
- Usuário fornece: nome da empresa, nome completo, email, senha
- Sistema cria tenant e usuário owner automaticamente
- Validação de email único no sistema
- Senha deve ter no mínimo 8 caracteres
- Retorna token de autenticação após registro

#### FR-001.2: Login
**Prioridade:** Alta  
**Descrição:** Autenticação de usuários existentes

**Critérios de Aceitação:**
- Usuário fornece email e senha
- Sistema valida credenciais
- Retorna token de API (Laravel Sanctum)
- Token válido por tempo configurável
- Rate limiting: máximo 5 tentativas por minuto

#### FR-001.3: Logout
**Prioridade:** Alta  
**Descrição:** Revogação de token de acesso

**Critérios de Aceitação:**
- Revoga token atual do usuário
- Opção de revogar todos os tokens do usuário
- Retorna confirmação de logout

#### FR-001.4: Convite de Membros
**Prioridade:** Alta  
**Descrição:** Proprietário pode convidar novos membros

**Critérios de Aceitação:**
- Apenas owner e admin podem convidar
- Email de convite enviado com link único
- Token de convite expira em 7 dias (configurável)
- Novo membro define senha no primeiro acesso
- Membro automaticamente associado ao tenant

#### FR-001.5: Transferência de Ownership
**Prioridade:** Média  
**Descrição:** Owner pode transferir propriedade do tenant

**Critérios de Aceitação:**
- Apenas owner atual pode transferir
- Novo owner deve ser membro ativo
- Owner anterior vira admin automaticamente
- Notificação enviada para ambos os usuários

---

### 3.2 Membros e Permissões (FR-002)

#### FR-002.1: Sistema de Roles
**Prioridade:** Alta  
**Descrição:** Hierarquia de papéis dentro do tenant

**Roles Disponíveis:**
- **Owner:** Controle total, incluindo gerenciamento de assinaturas
- **Admin:** Gerencia membros, projetos e workspaces
- **Member:** Cria e gerencia tarefas, acessa projetos atribuídos
- **Viewer:** Apenas visualização, sem edições

**Critérios de Aceitação:**
- Cada usuário tem exatamente uma role por tenant
- Permissões herdadas hierarquicamente
- Sistema valida permissões em cada operação

#### FR-002.2: Permissões Granulares
**Prioridade:** Alta  
**Descrição:** Permissões específicas por recurso

**Permissões por Recurso:**
- **Workspaces:** view, create, update, delete
- **Projects:** view, create, update, delete, archive
- **Tasks:** view, create, update, delete, assign
- **Members:** view, invite, update, remove

**Critérios de Aceitação:**
- Permissões verificadas via Laravel Policies
- Mensagens de erro claras para ações não autorizadas
- Owner tem todas as permissões automaticamente

#### FR-002.3: Listagem de Membros
**Prioridade:** Alta  
**Descrição:** Visualizar todos os membros do tenant

**Critérios de Aceitação:**
- Lista paginada de membros
- Mostra: nome, email, role, data de entrada
- Filtros por role e status (ativo/inativo)
- Busca por nome ou email

#### FR-002.4: Remoção de Membros
**Prioridade:** Alta  
**Descrição:** Remover membro do tenant

**Critérios de Aceitação:**
- Apenas owner e admin podem remover
- Não pode remover o próprio owner
- Tarefas atribuídas são reassinadas automaticamente
- Histórico de atividades preservado
- Notificação enviada ao membro removido

---

### 3.3 Workspaces (FR-003)

#### FR-003.1: CRUD de Workspaces
**Prioridade:** Alta  
**Descrição:** Gerenciamento de espaços de trabalho

**Critérios de Aceitação:**
- **Criar:** nome (obrigatório), descrição (opcional)
- **Listar:** paginação, filtros por nome
- **Atualizar:** nome, descrição, configurações
- **Deletar:** soft delete, requer confirmação
- Owner e Admin podem gerenciar

#### FR-003.2: Membros de Workspace
**Prioridade:** Alta  
**Descrição:** Associar membros específicos a workspaces

**Critérios de Aceitação:**
- Adicionar/remover membros do workspace
- Listar membros com suas roles
- Membro só vê projetos dos workspaces em que está
- Transferir membro entre workspaces

#### FR-003.3: Configurações de Workspace
**Prioridade:** Média  
**Descrição:** Personalizações por workspace

**Critérios de Aceitação:**
- Timezone padrão
- Formato de data preferido
- Configurações de notificação padrão
- Cores e temas (futuro)

---

### 3.4 Projetos (FR-004)

#### FR-004.1: CRUD de Projetos
**Prioridade:** Alta  
**Descrição:** Gerenciamento de projetos dentro de workspaces

**Critérios de Aceitação:**
- **Criar:** nome, descrição, workspace_id, deadline (opcional)
- **Listar:** filtros por workspace, status, deadline
- **Atualizar:** todos os campos editáveis
- **Deletar:** soft delete, arquiva todas as tarefas
- Paginação e busca por nome

#### FR-004.2: Status de Projetos
**Prioridade:** Alta  
**Descrição:** Controle de estado do projeto

**Status Disponíveis:**
- `active` - Projeto ativo em desenvolvimento
- `on_hold` - Pausado temporariamente
- `archived` - Finalizado ou cancelado

**Critérios de Aceitação:**
- Transições de status validadas
- Histórico de mudanças registrado
- Webhook disparado em mudanças de status

#### FR-004.3: Colunas Kanban
**Prioridade:** Alta  
**Descrição:** Colunas customizáveis por projeto

**Critérios de Aceitação:**
- Projeto tem colunas padrão: Backlog, To Do, In Progress, Review, Done
- Admin pode criar/editar/remover colunas
- Cada coluna tem: nome, ordem, cor
- Tarefas movem entre colunas
- Limite de tarefas por coluna (opcional)

#### FR-004.4: Membros de Projeto
**Prioridade:** Alta  
**Descrição:** Equipe alocada ao projeto

**Critérios de Aceitação:**
- Adicionar membros do workspace ao projeto
- Definir role específica no projeto (opcional)
- Listar membros com tarefas atribuídas
- Remover membros do projeto

---

### 3.5 Tarefas (FR-005)

#### FR-005.1: CRUD de Tarefas
**Prioridade:** Alta  
**Descrição:** Gerenciamento completo de tarefas

**Campos da Tarefa:**
- `title` (obrigatório)
- `description` (opcional, suporta markdown)
- `priority` (low, medium, high, urgent)
- `deadline` (opcional)
- `project_id` (obrigatório)
- `column_id` (obrigatório)
- `parent_id` (para subtarefas)

**Critérios de Aceitação:**
- Validações em todos os campos
- Tarefas pertencem a um projeto e coluna
- Soft delete preserva histórico
- Notificação aos assignees ao criar/editar

#### FR-005.2: Subtarefas
**Prioridade:** Média  
**Descrição:** Tarefas podem ter subtarefas recursivas

**Critérios de Aceitação:**
- Campo `parent_id` relaciona tarefa pai
- Subtarefas herdam projeto da tarefa pai
- Listar subtarefas de uma tarefa
- Progresso da tarefa pai baseado em subtarefas (opcional)

#### FR-005.3: Atribuição de Tarefas
**Prioridade:** Alta  
**Descrição:** Tarefas podem ser atribuídas a múltiplos membros

**Critérios de Aceitação:**
- Uma tarefa pode ter zero ou mais assignees
- Apenas membros do projeto podem ser atribuídos
- Notificação enviada ao atribuir
- Listar tarefas por membro

#### FR-005.4: Tags e Labels
**Prioridade:** Média  
**Descrição:** Sistema de tags para categorização

**Critérios de Aceitação:**
- Tags customizáveis por tenant
- Múltiplas tags por tarefa
- Cores personalizadas para tags
- Filtrar tarefas por tags

#### FR-005.5: Anexos
**Prioridade:** Alta  
**Descrição:** Upload de arquivos em tarefas

**Critérios de Aceitação:**
- Suporte a imagens, PDFs, documentos
- Limite de 10MB por arquivo
- Múltiplos anexos por tarefa
- Lista anexos com preview (para imagens)
- Delete de anexos (soft delete)

#### FR-005.6: Comentários
**Prioridade:** Alta  
**Descrição:** Discussão dentro de tarefas

**Critérios de Aceitação:**
- Comentários suportam markdown
- Menção a membros com @username
- Notificação ao ser mencionado
- Editar/deletar próprios comentários
- Histórico preservado

#### FR-005.7: Histórico de Alterações
**Prioridade:** Alta  
**Descrição:** Log completo de mudanças na tarefa

**Critérios de Aceitação:**
- Registra: quem, quando, o que mudou
- Mostra valores antes/depois
- Timeline visual de alterações
- Filtros por tipo de alteração
- Usa Spatie Activity Log

---

### 3.6 Notificações (FR-006)

#### FR-006.1: Notificações In-App
**Prioridade:** Alta  
**Descrição:** Sistema de notificações dentro da aplicação

**Eventos que Geram Notificação:**
- Atribuição de tarefa
- Menção em comentário
- Deadline se aproximando (24h antes)
- Mudança de status em tarefa atribuída
- Convite para projeto

**Critérios de Aceitação:**
- Notificações armazenadas em banco
- Status: lida/não lida
- Endpoint para listar notificações
- Marcar como lida individualmente ou em lote
- Paginação

#### FR-006.2: Notificações por Email
**Prioridade:** Média  
**Descrição:** Envio de emails para eventos importantes

**Critérios de Aceitação:**
- Email para convites de membros
- Email para deadlines próximas (digest diário)
- Preferências de notificação por usuário
- Templates de email profissionais
- Processamento assíncrono via Queue

#### FR-006.3: Agendamento de Notificações
**Prioridade:** Alta  
**Descrição:** Jobs agendados para notificações automáticas

**Critérios de Aceitação:**
- Laravel Scheduler configurado
- Job diário para verificar deadlines
- Job para lembrete de tarefas em atraso
- Evitar duplicatas de notificações

---

### 3.7 Webhooks (FR-007)

#### FR-007.1: Cadastro de Webhooks
**Prioridade:** Alta  
**Descrição:** Tenant registra URLs para receber eventos

**Critérios de Aceitação:**
- Cadastrar URL e eventos de interesse
- Validação de URL válida
- Secret para assinatura HMAC
- Ativar/desativar webhook
- Listar webhooks cadastrados

#### FR-007.2: Eventos Disponíveis
**Prioridade:** Alta  
**Descrição:** Eventos que disparam webhooks

**Eventos:**
- `task.created`
- `task.updated`
- `task.completed`
- `task.deleted`
- `project.created`
- `project.archived`
- `member.invited`
- `member.removed`

**Critérios de Aceitação:**
- Payload JSON padronizado
- Timestamp do evento
- ID do evento único
- Assinatura HMAC-SHA256

#### FR-007.3: Retry e Logs
**Prioridade:** Alta  
**Descrição:** Sistema de retry automático e logging

**Critérios de Aceitação:**
- Retry com backoff exponencial (3 tentativas)
- Log de cada tentativa com status HTTP
- Endpoint para consultar deliveries
- Webhook desativado após múltiplas falhas consecutivas
- Reenvio manual de eventos falhados

---

### 3.8 Relatórios e Dashboard (FR-008)

#### FR-008.1: Relatório de Tarefas
**Prioridade:** Média  
**Descrição:** Visualização agregada de tarefas

**Critérios de Aceitação:**
- Filtros: projeto, membro, status, período, prioridade
- Agrupamento por coluna, assignee, prioridade
- Total de tarefas e percentuais
- Gráfico de distribuição (dados para frontend)

#### FR-008.2: Tarefas em Atraso
**Prioridade:** Média  
**Descrição:** Lista de tarefas com deadline vencido

**Critérios de Aceitação:**
- Ordenação por dias de atraso
- Mostra assignees e projeto
- Paginação
- Exportável em CSV

#### FR-008.3: Métricas de Produtividade
**Prioridade:** Baixa  
**Descrição:** Indicadores de performance da equipe

**Métricas:**
- Tarefas completadas por período
- Tempo médio de conclusão
- Taxa de conclusão antes do deadline
- Distribuição de tarefas por membro

**Critérios de Aceitação:**
- Filtros por projeto e período
- Formato JSON para dashboards
- Cache de 1 hora

#### FR-008.4: Exportação CSV
**Prioridade:** Média  
**Descrição:** Export de relatórios em CSV

**Critérios de Aceitação:**
- Exporta resultado de qualquer relatório
- Headers em português
- Formato compatível com Excel
- Processamento assíncrono via Job para grandes volumes

---

## 4. Requisitos Não-Funcionais

### 4.1 Performance (NFR-001)
- Tempo de resposta de API < 200ms (p95)
- Queries ao banco < 100ms
- Suporte a 100 requisições/segundo por tenant
- Cache de dados frequentes (Redis)

### 4.2 Segurança (NFR-002)
- HTTPS obrigatório em produção
- Tokens JWT via Laravel Sanctum
- Rate limiting: 60 req/min por usuário
- Validação de inputs em todos os endpoints
- Sanitização de outputs (XSS)
- CORS configurado adequadamente
- Secrets apenas em .env

### 4.3 Escalabilidade (NFR-003)
- Suporte a 1000+ tenants simultâneos
- Processamento assíncrono de jobs pesados
- Horizontal scaling via containers Docker
- Queue workers escaláveis

### 4.4 Disponibilidade (NFR-004)
- Uptime >= 99.5%
- Backups diários automáticos
- Health check endpoint
- Logs centralizados
- Monitoramento com Laravel Horizon

### 4.5 Manutenibilidade (NFR-005)
- Código seguindo PSR-12
- Documentação inline (PHPDoc)
- README completo
- Swagger/OpenAPI atualizado
- Conventional Commits
- Cobertura de testes >= 80%

### 4.6 Usabilidade (NFR-006)
- Mensagens de erro descritivas
- Validações claras em português
- Paginação padronizada
- Respostas JSON consistentes
- Documentação de API navegável

---

## 5. Restrições e Dependências

### 5.1 Restrições Técnicas
- PHP 8.3 obrigatório
- MySQL 8.0 como banco de dados
- Redis para cache e filas
- Docker para containerização
- Linux como sistema operacional base

### 5.2 Dependências
- Laravel Sanctum para autenticação
- Spatie Permission para autorização
- Spatie Media Library para uploads
- Spatie Activity Log para histórico
- L5-Swagger para documentação
- Laravel Horizon para monitoramento de filas
- Pest PHP para testes

### 5.3 Premissas
- Usuário final usa client (web/mobile) que consome a API
- Frontend não faz parte deste escopo
- Deploy em ambiente com suporte a Docker
- Acesso a serviço de email (SMTP)

---

## 6. Casos de Uso Principais

### 6.1 UC-01: Onboarding de Novo Tenant
**Ator:** Nova Empresa  
**Fluxo:**
1. Empresa acessa formulário de registro
2. Preenche dados da empresa e do primeiro usuário
3. Sistema cria tenant e usuário owner
4. Owner recebe email de boas-vindas
5. Owner faz login e cria primeiro workspace
6. Owner convida membros da equipe
7. Membros aceitam convite e definem senha
8. Owner cria primeiro projeto

**Resultado:** Tenant configurado e pronto para uso

### 6.2 UC-02: Gestão Diária de Tarefas
**Ator:** Membro da Equipe  
**Fluxo:**
1. Membro faz login
2. Visualiza dashboard com tarefas atribuídas
3. Vê notificação de nova tarefa urgente
4. Abre a tarefa e lê descrição
5. Move tarefa para coluna "In Progress"
6. Adiciona comentário com atualização
7. Anexa arquivo de entrega
8. Move tarefa para "Review"
9. Marca tarefa como completada

**Resultado:** Tarefa completada e equipe notificada

### 6.3 UC-03: Integração via Webhook
**Ator:** Sistema Externo  
**Fluxo:**
1. Admin cadastra webhook endpoint
2. Configura eventos de interesse (task.completed)
3. Membro completa uma tarefa
4. Sistema dispara webhook
5. Payload assinado com HMAC enviado
6. Sistema externo valida assinatura
7. Sistema externo processa evento
8. Retorna status 200

**Resultado:** Integração em tempo real funcionando

---

## 7. Roadmap de Features

### Fase 1 - MVP (Meses 1-2)
- Setup e infraestrutura Docker
- Autenticação e tenants
- Membros e permissões básicas
- Workspaces CRUD
- Projetos CRUD
- Tarefas CRUD básico
- Testes unitários e feature

### Fase 2 - Core Features (Mês 3)
- Subtarefas
- Atribuição múltipla
- Tags e labels
- Comentários com menções
- Anexos de arquivos
- Activity log completo

### Fase 3 - Notificações e Integrações (Mês 4)
- Notificações in-app
- Notificações por email
- Sistema de webhooks
- Retry e logging de webhooks

### Fase 4 - Analytics e Polimento (Mês 5)
- Relatórios e métricas
- Exportação CSV
- Documentação Swagger
- Otimizações de performance
- Deploy em produção

---

## 8. Critérios de Aceitação Gerais

### 8.1 Qualidade de Código
- [ ] PSR-12 compliance
- [ ] PHPStan level 5 sem erros
- [ ] Cobertura de testes >= 80%
- [ ] Sem commits diretos na main
- [ ] Code review obrigatório

### 8.2 Documentação
- [ ] Todos os endpoints documentados no Swagger
- [ ] README com instruções de setup
- [ ] .env.example atualizado
- [ ] Diagramas de arquitetura
- [ ] Changelog mantido

### 8.3 Deploy
- [ ] Pipeline CI/CD funcionando
- [ ] Testes executados automaticamente
- [ ] Deploy automático em staging
- [ ] Health checks configurados
- [ ] Logs centralizados

---

## 9. Riscos e Mitigações

### 9.1 Risco: Performance com Muitos Tenants
**Impacto:** Alto  
**Probabilidade:** Média  
**Mitigação:**
- Índices otimizados no banco
- Cache agressivo com Redis
- Query optimization desde o início
- Load testing antes do launch

### 9.2 Risco: Isolamento de Dados Quebrado
**Impacto:** Crítico  
**Probabilidade:** Baixa  
**Mitigação:**
- Global Scopes em todos os models
- Testes específicos de isolamento
- Code review rigoroso
- Audit logs

### 9.3 Risco: Webhooks Falhando
**Impacto:** Médio  
**Probabilidade:** Alta  
**Mitigação:**
- Sistema de retry robusto
- Logs detalhados
- Alertas automáticos
- Interface de reprocessamento

---

## 10. Glossário

| Termo | Definição |
|-------|-----------|
| Tenant | Organização/empresa que utiliza a plataforma de forma isolada |
| Workspace | Espaço de trabalho dentro de um tenant para organização de projetos |
| Owner | Papel de maior privilégio, proprietário do tenant |
| Assignee | Membro atribuído a uma tarefa específica |
| Column | Coluna do board Kanban onde tarefas são organizadas |
| Global Scope | Filtro automático aplicado em todas as queries do Eloquent |
| Webhook | HTTP callback para notificar sistemas externos sobre eventos |
| HMAC | Hash-based Message Authentication Code para assinatura de payloads |

---

## 11. Aprovações

| Papel | Nome | Data | Assinatura |
|-------|------|------|------------|
| Product Owner | A definir | - | - |
| Tech Lead | A definir | - | - |
| Stakeholder | A definir | - | - |

---

**Próximos Passos:**
1. Revisão e aprovação deste PRD
2. Criação do Software Design Document (SDD)
3. Quebra em features e criação de user stories
4. Início do desenvolvimento em sprints
