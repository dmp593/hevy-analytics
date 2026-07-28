# Roadmap & estado — memória durável do projeto

> Atualizado a 2026-07-28. Este ficheiro existe para nada se perder entre
> sessões: estado real, pendentes de cada lado, e planos discutidos mas ainda
> não executados. Em português porque o dono do produto lê em português.

## Em produção (tudo verde, 911 testes)

| Área | Estado |
|---|---|
| Fotos permanentes (Cloudflare R2) | ✓ provado com teste real end-to-end |
| Unidades métrico/imperial | ✓ biometria E cargas de treino; escrita para o Hevy fica sempre métrica |
| Importação CSV multi-app | ✓ Hevy, Strong, FitNotes, Jefit + ecrã de mapeamento manual |
| Check-ins | ✓ 4 poses (frente/costas/esq/dir) + 16 medidas manuais, data editável |
| Comparador | ✓ 2–4 datas, poses alinhadas, só o peso é julgado (contra o objetivo, banda ~1%) |
| Landing + guia | ✓ refletem tudo o acima, nas duas línguas |
| Robustez | ✓ app sobrevive a rotação de APP_KEY; testes imunes ao ambiente da máquina |

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

### 1. Conversor entre plataformas (feature paga) — PLANEADO, aguarda "avança"

Converter dados entre Hevy ↔ Strong ↔ FitNotes ↔ Jefit ↔ CSV mapeado, para
quem muda de app não perder o histórico. O plano completo está em
docs/DATA-SOURCES.md → "Converter entre plataformas". Resumo da viabilidade:

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

### 2. Importação de nutrição por CSV (MyFitnessPal / Cronometer)

Totais diários → intake_logs. Adiado pelo dono como menos importante.

### 3. Ideias sem compromisso

Apagar treinos importados (limpa o "Prod Probe" de 2026-07-20 na conta do
dono); validação fina dos dialetos com ficheiros reais.

## Regras operacionais que já custaram caro (não repetir)

- O ambiente das sessões renasce em snapshots antigos: **começar QUALQUER
  sessão com `git fetch origin main && git reset --hard origin/main`**, e
  nunca confiar em leituras de ficheiros feitas antes disso.
- Postgres de testes local: porta 5433, socket /tmp, dados em
  /var/lib/postgresql/hevy (`pg_ctl -o '-p 5433 -k /tmp'`).
- Segredos nunca em comandos nem no chat; o painel do Render é o sítio deles.
- O deploy dispara-se pela API do Render (POST /deploys) — mudanças de env
  group nem sempre redeployam sozinhas.
