# Roadmap & estado — memória durável do projeto

> Atualizado a 2026-07-29. Este ficheiro existe para nada se perder entre
> sessões: estado real, pendentes de cada lado, e planos discutidos mas ainda
> não executados. Em português porque o dono do produto lê em português.

## Em produção (tudo verde, 1041 testes)

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
