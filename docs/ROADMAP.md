# Roadmap & estado — memória durável do projeto

> Atualizado a 2026-07-29. Este ficheiro existe para nada se perder entre
> sessões: estado real, pendentes de cada lado, e planos discutidos mas ainda
> não executados. Em português porque o dono do produto lê em português.

## Em produção (tudo verde, 1047 testes)

| Área | Estado |
|---|---|
| Fotos permanentes (Cloudflare R2) | ✓ provado com teste real end-to-end |
| Unidades métrico/imperial | ✓ biometria E cargas de treino; escrita para o Hevy fica sempre métrica |
| Importação CSV multi-app | ✓ Hevy, Strong, FitNotes, Jefit + ecrã de mapeamento manual |
| Check-ins | ✓ 4 poses (frente/costas/esq/dir) + 16 medidas manuais, data editável |
| Comparador | ✓ 2–4 datas, poses alinhadas, só o peso é julgado (contra o objetivo, banda ~1%) |
| Landing + guia | ✓ refletem tudo o acima, nas duas línguas |
| Robustez | ✓ app sobrevive a rotação de APP_KEY; testes imunes ao ambiente da máquina |
| Auditoria científica (2026-07-29) | ✓ as 7 recomendações implementadas — ver secção abaixo |

Infra: Render (srv-d9jtgmvavr4c73a6sd0g) + Neon (Postgres) + R2; variáveis num
environment group "hevy-analytics" no Render. Preço decidido: €9/mês, trial de
14 dias sem cartão. Nome: **fica "Hevy Analytics" por agora** (alternativas
discutidas: Lift Insight, Setwise, SetSense, TrainSight — decisão adiada).

## Pendentes do dono (nenhum bloqueia o produto)

1. **Apagar a branch antiga** `claude/deepseek-refactor-planning-dv6ufi` no
   GitHub (Branches → caixote). O proxy git das sessões não permite fazê-lo.
2. **Rotações de segurança** (tudo isto passou por conversas): chave API do
   Render, passwords das duas contas, token R2, password da Neon. A app
   aguenta qualquer rotação sem downtime.
3. **Exports reais** do Strong, FitNotes e Jefit — os importadores foram
   construídos sobre formatos documentados; um ficheiro real de cada valida
   em minutos.
4. **Paddle** (pagamentos reais) e **Resend** (emails reais; depois desligar
   `AUTO_VERIFY_EMAIL`) — passos em docs/SERVICES.md.
5. **Chave Hevy no perfil**: se aparecer "por definir" após o incidente do
   ambiente, voltar a colá-la na app (nunca no chat).

## Lotes futuros já discutidos (por ordem do dono)

### 1. Conversor entre plataformas (feature paga) — ✓ EM PRODUÇÃO (Jefit em beta)

Decisões do dono (2026-07-28): validação com beta testers dele (já existe um
para FitNotes; erros serão reportados); Jefit lança como beta; o risco dos
nomes de exercícios é aceite (v1 passa nomes tal-e-qual).

Converter dados entre Hevy ↔ Strong ↔ FitNotes ↔ Jefit ↔ CSV mapeado, para
quem muda de app não perder o histórico. Implementado: /convert (preview grátis com manifesto de perdas; download pago
via entitlements). Plano original em docs/DATA-SOURCES.md. Resumo:

- Ler qualquer formato já sabemos (CsvImport). O conversor são ESCRITORES
  por dialeto sobre o modelo normalizado + um manifesto de perdas honesto
  ("converter para FitNotes perde hora, título do treino, RPE e tipos de
  série — X séries afetadas"), calculado sobre os dados reais.
- Fidelidade por destino: Hevy (quase tudo) > Strong (perde supersets/tipos
  além de warmup) > FitNotes (perde títulos, horas, RPE, tipos, notas;
  ganha Category derivada dos nossos músculos) > Jefit (o mais pobre:
  séries empacotadas "50x10,55x8", só data+exercício+carga+reps).
- O risco real não é exportar — é o IMPORT das apps de destino (cabeçalhos
  exatos, formatos de data, matching de nomes de exercícios). Só se valida
  com as apps reais na mão: nós garantimos o round-trip pelo nosso próprio
  parser em testes; a aceitação por cada app de destino é validação manual.
- Nomes de exercícios: v1 passa os nomes tal-e-qual (a app destino cria
  exercícios personalizados — histórico intacto, sem ligação ao catálogo
  nativo dela); v2 opcional: tabela curada de correspondências para os ~100
  exercícios mais comuns por par de apps.
- Gate de pagamento no download, pelo chokepoint de entitlements existente.

### 2. Nutrição — ✓ EM PRODUÇÃO (CSV + FatSecret)

CSV de totais diários (MyFitnessPal premium export, Cronometer, Lose It!,
genérico) na página de Nutrição — linhas por refeição/alimento somadas por
dia, idempotente, nunca toca em pesos registados. E ligação FatSecret por
OAuth 1.0 (a 2.0 exige whitelist de IP): ligar no Perfil, sync noturno dos
últimos 7 dias via fatsecret:sync agendado. Assinatura validada contra o
vetor publicado do OAuth 1.0a E contra o endpoint real (request_token 200).
PENDENTE DE VALIDAÇÃO REAL: o dono ligar a conta dele em produção (o passo
authorize/access só se prova com um browser).

### 3. Auditoria científica — ✓ EM PRODUÇÃO (2026-07-29)

O dono pediu "avança com todas as tuas recomendações". As 7 entregues:

1. **Peso de tendência (EWMA)** nos mostradores do dashboard e da página
   Corpo — média com meia-vida de 10 dias, consciente de intervalos entre
   pesagens; os gráficos continuam com as leituras em bruto.
2. **Cintura/anca exposto** com limiares da OMS por sexo (0,90 H / 0,85 M);
   sem sexo definido mostra o número sem cor (sem julgamento desonesto).
3. **RFM** (Woolcott & Bergman 2018) como terceiro estimador de gordura ao
   lado da balança e do Navy — nunca substituído em silêncio.
4. **Mistura adaptativa ponderada pelos dados**: o TDEE adaptativo pesa
   0,35 com 7 dias de registos e até 0,80 com 28 (antes era 50/50 fixo);
   `basis` guarda peso e nº de dias para transparência.
5. **Massa gorda julgada no comparador** contra o objetivo (banda 1pp; num
   bulk a subida só é âmbar acima de 2pp, nunca vermelha).
6. **Alerta de pico de volume**: séries dos últimos 7 dias ≥1,6× a média
   semanal do mês anterior (base ≥8 séries/sem; exige ≥3 semanas de
   histórico para não acusar quem começa a treinar).
7. **Alerta de estagnação de e1RM**: top-3 levantamentos sem tendência de
   subida em 8 semanas (≥6 sessões); suprimido em cut, onde manter força
   já é sucesso.

Recusadas com fundamento (não reabrir sem pedido): idade metabólica, BRI,
score compósito único.

### 3b. Investigação de funcionalidades — ✓ EM PRODUÇÃO (2026-07-29)

Pedido: "faz investigações nesta área e vê que funcionalidades adicionavas",
seguido de "analisa exaustivamente o projeto para validar se já está
implementado. caso não esteja, implementa". A análise exaustiva encontrou:

- **Já existia**: balanço push/pull + quad/posterior + superior/inferior
  (MuscleBalance); motor de progressão com dupla progressão e write-back
  confirmado para o Hevy (RoutineProgression + write.progression).
- **Implementado agora**:
  1. Progressão consciente do desempenho: a sugestão só sobe quando a última
     sessão registada cumpriu a prescrição; falhou → repete; cumpriu a
     RPE ≥ 9,5 → consolida; sem registo → progride como antes.
  2. Cartão de consistência no dashboard (sessões da semana, média 4 sem,
     semanas seguidas, músculos a ~2×/sem; nota para contas nas primeiras
     4 semanas). Base: guidelines ACSM 2026 + coorte de adesão 2025.
  3. Esforço (RPE) na página de músculos: % de séries a 4+ reps da falha
     por músculo (Robinson 2024); silencioso com cobertura de RPE < 50%.
  4. Deteção de RPE a subir com carga igual num lift estagnado → sugestão
     honesta de deload no alerta (enquadramento Coleman 2024).
  5. Import CSV de passos/sono (Health Auto Export, Fitbit, genérico) para
     o intake log + médias de 14 dias na página de Nutrição + verificação
     do nível de atividade vs. passos observados.
- **Emails prontos (2026-07-29, pedido do dono)**: check-in semanal construído
  e agendado (segundas 08:00, com watermark idempotente, opt-out no perfil,
  bilingue). Com MAIL_MAILER=log tudo corre inofensivo; ativar Resend
  (docs/SERVICES.md) liga a entrega sem mais deploys.
- **Progressão visível + back-off (2026-07-29)**: as recomendações de peso ×
  reps aparecem na página da rotina antes de qualquer staging; nova regra —
  lift estagnado (8 sem) + última sessão a RPE 9,5+ → recuar ~7,5% (arredondado
  a 2,5 kg) para reconstruir com 1-2 reps na reserva (Refalo 2024). É o
  "desce 10 kg no deadlift" do DeepSeek, sistematizado.
- **Navegação reorganizada (2026-07-29, "avança" do dono)**: 4 secções — Hoje /
  Treino / Corpo (agora com Projeções) / **Nutrição promovida a secção** —,
  grupo "Os meus dados" no menu do avatar (Importar, Converter, Escritas no
  Hevy, Exportar) para as portas de dados deixarem de estar invisíveis, e
  barra de separadores fixa em baixo no telemóvel (as 4 secções, com ícones).
- **Recusado/adiado**: "frescura muscular" tipo Fitbod (heurística vestida de
  fisiologia — só com pedido explícito).

### 4. Ideias sem compromisso

Apagar treinos importados (limpa o "Prod Probe" de 2026-07-20 na conta do
dono); validação fina dos dialetos com ficheiros reais.

### 5. "Melhor que a concorrência" — EM CURSO (2026-07-29)

Pedido: usar o CSV real do dono (137 treinos, 18 meses, RPE em 16% das
séries) + estudar os projetos GitHub concorrentes (Hevy Insights,
HevyWorkoutAnalyzer, Data Visualiser) e o Loadline, e construir o que falta
ou está pior, com interface mais apelativa. Entregue já: **heatmap de
calendário** (26 semanas, estilo GitHub, server-rendered) + **ritmo de
treino** (mediana de duração, horas e dias típicos) no dashboard, secção
Consistência. Lote 2 entregue (2026-07-29): **calendário clássico** em alternativa ao
heatmap (escolha no Perfil, users.calendar_style — pedido do dono a meio),
**quadro de estado dos exercícios** na Performance (triagem a
subir/estável/a descer sobre as mesmas regressões dos alertas, pior
primeiro) e **sobrecarga progressiva por músculo** (média dos declives de
e1RM ponderada por séries — o "POI" do Loadline em versão transparente).
Da lista do agente ficam por fazer, todos ciência-compatíveis e só-CSV:
cartões partilháveis/"Year Wrapped", delta semanal por músculo, scatter
peso×reps, timeline de PRs por mês, analytics por equipamento. Recusado:
dashboard de widgets drag-and-drop (build pesado, zero ciência). CSV do
dono em scratchpad/hevy.csv (não comitar!).

### 6. Trio "ciência pessoal" — ✓ EM PRODUÇÃO (2026-07-29)

Plano fechado (implementar exatamente isto):
- Novo serviço App\Services\Analytics\PersonalScience, 3 leituras sobre
  SetQuery (janela 6 meses; e1RM fiável por sessão como % da média do
  próprio levantamento):
  1. recoveryCurve(): top-3 lifts (≥8 sessões); dias de descanso desde a
     sessão anterior DESSE lift → e1RM relativo; buckets 1/2/3/4+ dias, só
     com n≥3; saída "com 2 dias: +1,2% vs média (n=9)".
  2. timeOfDay(): buckets manhã <12h / tarde 12-17h / noite ≥17h (tz do
     utilizador); lifts com ≥4 sessões em ≥2 buckets; diferença média
     ponderada. Citar Grgic 2019 e dizer se NO utilizador se confirma.
  3. repRangePortfolio(): 12 semanas; bandas 1-5/6-12/13-20/21+; % por
     músculo; nota goal-aware (força sem 1-5 → aviso; hipertrofia: espetro
     todo serve, Schoenfeld 2021).
- UI: Performance ganha secção "A tua ciência" (1+2); Músculos ganha o
  portfólio (3). Lang EN+PT com fontes. Testes dispara/não-dispara.
- Entregue como especificado: PersonalScience + secção "A tua ciência"
  na Performance + portfólio na página de Músculos, 6 testes.

### 7. Dupla auditoria — PLANO INTEIRO APROVADO; C1+E1 ✓ ENTREGUES (2026-07-29)

Nota: auditores correram em snapshot antigo; achados revalidados linha a
linha contra o HEAD. Core científico confirmado correto (Epley/Brzycki,
Mifflin, Katch, Navy, Boer, FFMI, Wilks 2020, DOTS coefs, OLS, adaptativo).

**FASE C1 — correções científicas — ✓ FEITO (flat=1×SE da regressão; DOTS 150F; range→fundo+RIR2; delta×span; pesos de intake na tendência; sem goal sem veredicto; floor gordura; presets alinhados; atribuições honestas RP/FFMI/Cunningham/percentil):**
a) StrengthAnalytics:174 banda "flat" vale 7× o documentado. Fix
   principiado: flat = |slope| < 1×SE da regressão; atualizar lang de
   board/overload + testes.
b) StrengthScore:47 clamp DOTS mulheres = 150 kg (fonte OPL dots.rs).
c) RoutineProgression:122 `! $weight && $range && $e1rm` (não sobrescrever
   carga planeada); prescrever fundo do range com desconto RIR-2
   (loadForReps($e1rm, reps+2)); remover bump 1.02.
d) BodyCompAnalytics:309 delta = slope × span observado, não janela pedida.
e) weightRateKgPerWeek funde pesos de intake_logs (o guia promete-o).
f) GoalAlerts:35 sem goal → sem alertas de ritmo (não default 0.35).
g) Macros: aplicar floor gordura 0,5 g/kg; label 7700→"tecido adiposo";
   Cunningham→Katch-McArdle no guia.
h) GoalProfile: alinhar surplus % ↔ target rate pelos próprios cálculos.
i) MuscleLandmarks: alinhar com RP publicado ou "adaptado de RP" no guia;
   nota 0,5 secundários vs landmarks diretos; caveat FFMI feminino;
   guia "percentil literal"→aproximação.

**FASE E1 — ✓ FEITO (BodyCompAnalytics::for + all() 1 query; activeGoal memo; computeTargets persist:false em GET + updateOrCreate; índices sync_logs/goals/routine_exercises/photos/write_ops + prune 14d; AnalyticsCache versão+data com bumps em sync/intake/measurements/goals/imports/fatsecret; dashboard+muscle cacheados):**
a) BodyCompAnalytics memoizado por request (measurements() 1 query) +
   app()->scoped(); activeGoal() memoizado.
b) computeTargets read-only em GET (persistir só em POST/sync/intake).
c) Índices: sync_logs(user_id,id), goals(user_id), routine_exercises
   (routine_id), progress_photos(user_id), write_operations(user_id);
   remover 2 duplicados; pruning de sync_logs.
d) Cache de payloads por página: user:{id}:v{ver}:{filtro}, TTL 24h,
   versão bumpada em sync/intake/measurements/goals/imports.

**FASE E2 — rollup ESCRITO ✓ (2026-07-30); falta ligar os LEITORES:**
Tabela workout_set_rollups (user/dia-local/exercício: sets, reps, tonnage,
best_weight, best_reps, músculo) + RollupBuilder::rebuild() (1 query
INSERT..SELECT, transacional, idempotente) chamado no HevySync::run e nos
dois caminhos do ImportController, antes do bump de cache. e1RM fiável
fica DELIBERADAMENTE fora (precisa dos clamps RPE do OneRepMax — força lê
raw). VolumeAnalytics ✓ LIGADO ao rollup (tonnage/sets/reps/série, com
backfill-on-first-read para contas que nunca ressincronizaram);
ConsistencyAnalytics/TrainingRhythm ficam raw de propósito (precisam de
horas/durações por sessão que o rollup não carrega) — E2 dado por
CONCLUÍDO. Bootstrap agora cura is_admin + comp de operador em contas
existentes (as duas contas do dono estavam sem flag/licença por terem
sido criadas antes das vars — corrige-se sozinho no arranque).
a) Agregação em SQL (SUM/COUNT/date_trunc) para tonelagem/séries.
b) Rollup workout_set_rollups(user, dia, exercício, músculo, sets, reps,
   tonnage, best_e1rm) mantido no HevySync.
c) FitnessVolt ✓ FEITO em versão mínima (chave em buckets de 2,5 kg, TTL 7 dias, timeout 3 s sem retry — fallback builtin cobre o resto); refresh assíncrono fica opcional.

**FASE E3 — infra por patamar (aprovada; passos do dono quando houver tráfego):**
- Já: Render pago + worker/scheduler dedicado (hoje NÃO há worker em
  prod!), Neon pooled (PgBouncer); PHOTO_DISK=r2 confirmado.
- ~500-2000: Redis (cache+sessions+queue), FrankenPHP worker/Octane.
- ~2000-10000: Neon autoscaling + web horizontal.

## Dívida de legibilidade conhecida (auditoria 2026-07-29, passe 2 pendente)

Dois revisores independentes: veredicto "não é esparguete, arquitetura boa,
mas a mesma regra escrita em vários sítios". Corrigido no passe 1: sexo
normalizado num único sítio (era 7), regra de "lift estagnado" unificada,
CompareController extraído para CheckInComparison, top-lift dedupe,
status() 3×→1, MuscleVerdict lê labels do MuscleBalance, 6 strings inglesas
hardcoded traduzidas, 3 métodos mortos apagados, .form-file no CSS.
Fica para um passe 2 (médio, sem urgência): extrair um CsvReader comum aos
3 importadores; BodyCompAnalytics::status() reusar os próprios acessores
(fat-source e sítio Navy duplicados internamente); partir GoalAlerts::all()
em famílias; 4 botões feitos à mão → x-ui.button.

## Princípio de produto (dono, 2026-07-29, permanente)

"O meu ponto forte será sempre sustentar toda a informação gráfica e textual
com base na ciência e fórmulas matemáticas comprovadas." Critério de
aceitação para QUALQUER métrica/gráfico/frase novos: fórmula com nome +
fonte citável (paper/guideline), incerteza declarada quando existe, e recusa
explícita de números mágicos ou compósitos opacos — mesmo que a concorrência
os tenha e sejam vistosos. Já recusados ao abrigo disto: idade metabólica,
BRI, score único, "frescura muscular" tipo Fitbod.

## Regras operacionais que já custaram caro (não repetir)

- O ambiente das sessões renasce em snapshots antigos: **começar QUALQUER
  sessão com `git fetch origin main && git reset --hard origin/main`**, e
  nunca confiar em leituras de ficheiros feitas antes disso.
- Postgres de testes local: porta 5433, socket /tmp, dados em
  /var/lib/postgresql/hevy (`pg_ctl -o '-p 5433 -k /tmp'`).
- Segredos nunca em comandos nem no chat; o painel do Render é o sítio deles.
- O deploy dispara-se pela API do Render (POST /deploys) — mudanças de env
  group nem sempre redeployam sozinhas.
