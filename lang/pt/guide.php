<?php

/*
 | O guia, em português europeu (pt_PT).
 |
 | As siglas científicas — MV, MEV, MAV, MRV, BMR, TDEE, FFMI, RPE, RIR, e1RM —
 | mantêm-se em inglês porque é assim que aparecem na literatura e é assim que
 | são usadas no ginásio. Traduzi-las à força tornaria o guia mais difícil de
 | ler, não mais fácil, e deixaria de coincidir com o que a aplicação mostra.
 */

return [

    'intro' => 'Sem jargão. Esta página explica cada número que a aplicação mostra, porque é que importa para <strong>ganhar músculo mantendo-te seco</strong>, e como é que "bom" se parece. Tudo assenta em investigação publicada de força e nutrição (fontes no fim).',

    'nav' => [
        'data' => 'Trazer os dados',
        'checkins' => 'Check-ins & comparação',
        'volume' => 'Séries semanais & volume',
        'strength' => 'Força & 1RM',
        'levels' => 'Níveis de força',
        'body' => 'Composição corporal',
        'accuracy' => 'Rigor das medições',
        'leanbulk' => 'Sinais de ganho limpo',
        'nutrition' => 'Calorias & macros',
        'projections' => 'Projeções',
        'balance' => 'Equilíbrio muscular',
    ],

    'data' => [
        'title' => 'Trazer os dados — sincronização, importações & unidades',
        'lead' => 'Há duas portas de entrada na app, e ambas acabam exatamente no mesmo sítio:',
        'api' => '<strong>Chave de API do Hevy (Hevy Pro):</strong> cola-a uma vez no Perfil e todos os treinos sincronizam sozinhos, medições corporais incluídas.',
        'csv' => '<strong>Importação CSV (qualquer conta):</strong> envia a exportação que a tua app de treino produz na página :import. Ficheiros do <strong>Hevy, Strong, FitNotes e Jefit</strong> são reconhecidos automaticamente pelas colunas; qualquer outro CSV recebe um <strong>ecrã de correspondência de colunas</strong> — apontas cada campo (data, exercício, peso, repetições…) para a coluna certa e importas na mesma.',
        'protections' => 'Detalhes que protegem os teus números:',
        'idempotent' => '<strong>Reenviar é sempre seguro.</strong> A identidade de um treino é a sua data e nome, por isso o mesmo ficheiro — ou uma exportação mais recente que o sobreponha — funde-se em vez de duplicar.',
        'units_ask' => '<strong>As unidades são perguntadas quando o ficheiro não as diz.</strong> A exportação do Strong no iPhone, por exemplo, não traz coluna de unidade; o formulário pergunta se os pesos estão em kg ou lb, pré-definido com a tua preferência.',
        'units_pref' => '<strong>A tua preferência de unidades</strong> (Perfil → Unidades, ou o interruptor no cartão de boas-vindas) muda como escreves e lês tudo — altura em ft/in, peso corporal e cargas em lb, perímetros em polegadas. Internamente fica tudo em métrico, para os cálculos nunca misturarem unidades, e o que é escrito de volta no Hevy segue em métrico porque a API deles assim fala.',
        'muscles' => '<strong>Os músculos são inferidos dos nomes dos exercícios</strong> nas importações CSV — os ficheiros não os trazem. Nomes padrão associam bem; se mais tarde adicionares uma chave de API, a atribuição do próprio Hevy assume.',
    ],

    'checkins' => [
        'title' => 'Check-ins, fotografias & comparação',
        'lead' => 'Um <strong>check-in</strong> é uma data com até quatro fotografias — <strong>frente, costas, lado esquerdo, lado direito</strong> — mais um peso corporal e uma nota. Uma única fotografia chega para guardar; tirar as quatro dos mesmos sítios, com a mesma luz, é o que torna as comparações honestas.',
        'measurements' => '<strong>Medidas manuais</strong> (página Corpo → Registar medidas): peso, massa gorda e catorze perímetros, todos os campos opcionais. A <strong>data é editável</strong> — uma medição de sábado registada na segunda pertence a sábado — e voltar a guardar uma data completa ou corrige sem tocar nos campos que deixaste em branco.',
        'compare' => '<strong>Comparar</strong> (:compare): escolhe 2–4 datas de check-in e elas alinham-se lado a lado — todas as fotografias de "frente" numa linha, todas as de "costas" na seguinte — com uma tabela de medidas por baixo a mostrar a variação de cada data face à mais antiga.',
        'judgement' => '<strong>Só a variação de peso é julgada, e só contra o teu objetivo:</strong> ganhar lê-se verde num bulk e vermelho num cut, a manutenção julga as duas direções por igual, e uma variação abaixo de cerca de 1% do peso corporal conta como estável — isso é água e refeições. Os perímetros nunca são coloridos, e a seta e o sinal dizem sempre o mesmo que a cor.',
    ],

    'volume' => [
        'title' => 'Séries semanais & landmarks de volume (MV · MEV · MAV · MRV)',
        'lead' => 'O maior determinante do crescimento muscular é <strong>quantas séries duras fazes por músculo em cada semana</strong> (uma "série dura" é uma série de trabalho a sério, perto da falha; aquecimentos não contam). A investigação descreve quatro landmarks de séries semanais por músculo:',
        'mv' => '<strong>MV — Volume de Manutenção:</strong> o mínimo indispensável para <em>manter</em> o músculo que já tens. Abaixo disto, perdes tamanho devagar.',
        'mev' => '<strong>MEV — Volume Mínimo Eficaz:</strong> o menor número de séries que constrói mesmo músculo novo. É a linha a partir da qual começas a crescer.',
        'mav' => '<strong>MAV — Volume Máximo Adaptativo:</strong> o ponto ideal, onde obténs o <em>máximo</em> de crescimento pelo esforço. A maior parte do teu treino deve ficar entre MEV e MAV.',
        'mrv' => '<strong>MRV — Volume Máximo Recuperável:</strong> o máximo que consegues fazer e ainda recuperar. Acima disto é "volume lixo" — mais fadiga, nenhum ganho extra.',
        'zone' => 'A zona ideal é portanto <strong>MEV → MAV</strong>. A aplicação classifica cada músculo:',

        'status' => [
            'below_maintenance' => '<strong>Abaixo da manutenção</strong> — séries a menos; o músculo está provavelmente parado ou a encolher. Acrescenta séries.',
            'maintenance' => '<strong>Manutenção</strong> — a aguentar, sem crescer de facto. Aceitável em défice; acrescenta séries em ganho.',
            'optimal' => '<strong>Ótimo</strong> — dentro da zona de crescimento MEV–MAV. Continua assim.',
            'growth' => '<strong>Crescimento (alto)</strong> — perto do teu teto de recuperação; bom se estiveres a recuperar bem.',
            'junk' => '<strong>Lixo</strong> — acima do MRV; corta séries, só estás a acumular fadiga.',
        ],

        'example_title' => 'Ler um exemplo: "Peito 7,9/sem · abaixo da manutenção (MEV 10 / MAV 16)"',
        'example_body' => 'Estás a fazer em média <strong>7,9 séries duras de peito por semana</strong>. O peito precisa de pelo menos <strong>cerca de 8 para manter</strong> e <strong>cerca de 10 (MEV) para crescer mesmo</strong>, com o melhor retorno até <strong>cerca de 16 (MAV)</strong>. A 7,9 estás <em>abaixo</em> da linha de crescimento, por isso o teu peito provavelmente não está a desenvolver-se ao ritmo que podia. A correção: acrescenta três a cinco séries de peito por semana — um press extra mais uma sessão de aberturas — para chegar a 11–14, e volta a ver daqui a algumas semanas.',
        'landmark_note' => 'Os landmarks por músculo seguem a Renaissance Periodization (Israetel et al.). São orientações de partida — a recuperação varia de pessoa para pessoa. Os músculos secundários contam como meia série por omissão.',

        'tonnage_title' => 'Tonelagem (volume-carga)',
        'tonnage_body' => '<strong>Tonelagem é carga × repetições, somada em todas as tuas séries.</strong> É o trabalho total feito e serve de aproximação rápida ao estímulo de treino. Tonelagem a subir ao longo de semanas e meses é sobrecarga progressiva: estás a fazer mais do que antes, e é isso que provoca crescimento.',
    ],

    'strength' => [
        'title' => 'Força & 1RM estimado',
        'e1rm_title' => '1RM estimado (e1RM)',
        'e1rm_body' => 'O teu <strong>máximo de uma repetição</strong> é o mais que conseguirias levantar uma única vez. Testá-lo é arriscado, por isso é <em>estimado</em> a partir de séries normais com duas fórmulas conhecidas (<strong>Epley</strong> e <strong>Brzycki</strong>), com média entre as duas. Por exemplo, 100 kg × 10 repetições dá aproximadamente um 1RM estimado de <strong>133 kg</strong>.',
        'e1rm_why' => 'Porque importa: o e1RM é a forma mais limpa de ver se estás a ficar <strong>mais forte ao longo do tempo</strong>, mesmo quando as repetições e as cargas variam. Uma linha de e1RM a subir é progresso de força real. Só são usadas séries de <strong>12 repetições ou menos</strong>, porque as fórmulas perdem rigor com repetições altas.',
        'rpe_title' => 'RPE & RIR',
        'rpe_body' => '<strong>RPE</strong> (esforço percebido, 1–10) é o quão dura foi a série. <strong>RIR</strong> (repetições em reserva) é quantas repetições ainda tinhas: RIR = 10 − RPE. Se registares o RPE, ele é usado para tornar a estimativa de e1RM mais rigorosa — uma série interrompida a três repetições da falha vale mais do que os números em bruto sugerem.',
        'wilks_title' => 'Wilks / DOTS & força relativa',
        'wilks_body' => 'Estes pontuam os teus levantamentos <strong>relativamente ao teu peso corporal</strong>, para que o progresso continue justo à medida que o teu peso muda. <strong>Força relativa</strong> é carga ÷ peso corporal — um supino a 1,5× o peso corporal, por exemplo. Isto conta durante um ganho, onde levantar mais <em>e</em> ganhar peso pode dourar o progresso; o Wilks e o DOTS cortam esse efeito.',
    ],

    'levels' => [
        'title' => 'Níveis de força (iniciante → elite)',
        'lead' => 'Para cada exercício com barra, ficas colocado numa <strong>barra de 0 a 100%</strong> que te compara com outros atletas do <strong>mesmo sexo, peso e idade</strong>. A percentagem preenchida é o teu percentil — <em>"mais forte do que X% dos atletas"</em>.',
        'boundaries' => 'As linhas de separação marcam as fronteiras entre níveis, mapeadas a percentis conhecidos:',

        'tier' => [
            'beginner' => '<strong>Iniciante</strong> — até cerca do percentil 20 (consegue executar o exercício, treina há cerca de um mês).',
            'novice' => '<strong>Novato</strong> — à volta do percentil 20 (treina com regularidade há cerca de seis meses).',
            'intermediate' => '<strong>Intermédio</strong> — à volta do percentil 50, o atleta treinado médio (cerca de dois anos).',
            'advanced' => '<strong>Avançado</strong> — à volta do percentil 80 (cinco ou mais anos de progresso).',
            'elite' => '<strong>Elite</strong> — percentil 95 ou acima (força de nível competitivo).',
        ],

        'how' => 'Assim, "mais forte do que 86%" cai na faixa <strong>avançada</strong>, a aproximar-se da elite. O teu 1RM é estimado (Epley/Brzycki) a partir da tua melhor série, dividido pelo peso corporal, e <strong>ajustado à idade</strong> para que sejas comparado com pares da tua idade — a força atinge o pico por volta dos 25 aos 35 anos e depois declina.',
        'sources' => '<strong>De onde vêm os dados (em camadas):</strong> primeiro é tentada a API gratuita do <strong>FitnessVolt</strong> (CC BY 4.0) — serve duas populações distintas: percentis de <strong>competição verificada</strong> do <strong>OpenPowerlifting</strong> (mais de 2,5 milhões de levantamentos com juiz) e percentis de <strong>ginásio auto-reportados</strong> (Symmetric Strength), ajustados à idade. Se estiver inacessível, a aplicação recorre a uma tabela local do <strong>OpenPowerlifting</strong> e depois a um modelo de rácios offline.',
        'why_two_title' => 'Porquê duas percentagens diferentes?',
        'why_two_body' => 'O mesmo levantamento fica em posições diferentes consoante com quem és comparado. Contra atletas de <strong>ginásio</strong> ficas alto; contra atletas de <strong>competição</strong> — um grupo bastante mais forte — ficas mais abaixo. Um supino de 100 kg com 68 kg de peso corporal fica cerca do <strong>percentil 83 (ginásio)</strong> mas cerca do <strong>46 (competição verificada)</strong>. O número de <strong>ginásio</strong> é o destacado, porque coincide com o que aplicações como o Hevy mostram, e o verificado aparece ao lado. Nenhum está "errado" — são populações de referência diferentes.',
        'footnote' => 'Os três grandes (agachamento, supino, peso morto) têm dados de competição verificados; os exercícios acessórios usam estimativas por rácio. Só são cobertos exercícios com barra em carga × repetições; tudo o resto recorre ao modelo offline. Com dados do FitnessVolt (CC BY 4.0); dados do OpenPowerlifting (CC0) e da Symmetric Strength.',
    ],

    'body' => [
        'title' => 'Composição corporal',
        'fat' => '<strong>% de gordura corporal:</strong> a parte do teu peso que é gordura. Mais baixo é mais seco. Durante um ganho limpo queres que suba só devagar.',
        'lean' => '<strong>Massa magra:</strong> tudo o que não é gordura — músculo, osso, água, órgãos. Aumentar massa magra com a gordura estável é o objetivo todo.',
        'navy' => '<strong>% de gordura Navy:</strong> uma estimativa independente a partir de medições com fita (pescoço, cintura, altura), mostrada como contraprova ao número da tua balança ou adipómetro.',
        'ffmi' => '<strong>FFMI (índice de massa livre de gordura):</strong> a tua muscularidade, ajustada à altura — como o IMC, mas para músculo. O <strong>FFMI normalizado</strong> padroniza para 1,80 m para ser comparável. Grosso modo: 19 é médio, 22 é em forma, e 25 anda perto do teto natural para a maioria dos homens.',
        'waist_height' => '<strong>Rácio cintura-altura:</strong> cintura ÷ altura. Mantê-lo <strong>abaixo de 0,5</strong> é um indicador simples de saúde. Se subir durante um ganho, estás a acumular gordura na zona abdominal.',
        'symmetry' => '<strong>Simetria esquerda/direita:</strong> a diferença percentual entre as medições dos membros esquerdo e direito. Acima de cerca de 5% sugere um desequilíbrio que merece trabalho unilateral.',
    ],

    'accuracy' => [
        'title' => 'Rigor das medições — porque é que um só número não chega',
        'lead' => 'As balanças inteligentes estimam a gordura corporal por <strong>BIA — análise de impedância bioelétrica</strong>: uma corrente mínima que passa pelos teus pés. É prático mas <strong>ruidoso, e pouco rigoroso em valores absolutos</strong>:',
        'bia_error' => 'Erra qualquer coisa como <strong>3 a 8 pontos percentuais</strong> de gordura corporal face a um exame laboratorial (DEXA).',
        'bia_feet' => 'As balanças de pé para pé lêem sobretudo o teu <strong>trem inferior</strong> e estimam o resto.',
        'bia_swing' => 'As leituras oscilam com <strong>hidratação, hidratos, sal, comida, hora do dia, temperatura e treino recente</strong> — muitas vezes mais do que a tua variação semanal real.',

        'protects_lead' => 'É por isso que uma única leitura do género "ganhaste 77% de gordura" pode ser quase toda ruído. Esta aplicação protege-te disso:',
        'protect_trends' => '<strong>Tendências, não dois pontos:</strong> a partição é calculada a partir de uma reta ajustada a <em>muitas</em> leituras.',
        'protect_confidence' => '<strong>Etiqueta de confiança:</strong> se não houver dados consistentes que cheguem, a estimativa é marcada como <em>pouco fiável</em> e o aviso suaviza-se.',
        'protect_triangulate' => '<strong>Triangulação:</strong> as tendências de peso, cintura, peito, braço e força são mostradas em conjunto — ganho de músculo parece-se com peito e braços mais força a subir enquanto a cintura fica estável.',
        'protect_source' => '<strong>Escolhe a tua fonte</strong> (Perfil → fonte de gordura corporal): <strong>balança (BIA)</strong>, <strong>fita Navy</strong> (pescoço/cintura/altura — mais estável), ou <strong>manual</strong> (escreves a tua própria estimativa).',

        'bottom_line' => '<strong>Em resumo:</strong> o espelho e as fotografias de progresso são legitimamente o indicador diário mais fiável. Usa a página :photos para isso, e trata a percentagem de gordura como uma tendência aproximada e não como um dogma.',
        'consistent_title' => 'Mede sempre da mesma maneira',
        'consistent_body' => 'À mesma hora do dia, <strong>em jejum, de manhã, depois da casa de banho</strong>, com hidratação semelhante. A consistência importa muito mais do que o valor absoluto.',
    ],

    'leanbulk' => [
        'title' => 'Sinais de ganho limpo',
        'rate' => '<strong>Ritmo de peso (%PC/semana):</strong> a que velocidade o teu peso corporal está a mudar, em percentagem do teu peso, por semana. Para um ganho limpo o ponto ideal é <strong>+0,25% a +0,5% por semana</strong> — cerca de 0,2 a 0,35 kg por semana a 70 kg. Mais rápido significa mais gordura; mais lento ou negativo significa que não estás a alimentar o crescimento.',
        'p_ratio' => '<strong>P-ratio (partição):</strong> do peso que ganhaste, a fração que foi massa <em>magra</em> em vez de gordura. Um p-ratio de 0,7 quer dizer que 70% do ganho foi músculo, o que é excelente. Um p-ratio baixo durante um ganho é um aviso para abrandar o excedente.',
        'waist' => '<strong>Cintura contra tendência muscular:</strong> se a tua cintura está a crescer mais depressa do que o peito e os braços, isso serve de indício de que o ganho de gordura está a ultrapassar o de músculo, e a aplicação assinala-o.',
        'note' => 'Estes precisam de alguns registos de peso e de medições ao longo do tempo para se tornarem fiáveis. Regista o teu peso com regularidade no Hevy, ou na página de nutrição.',
    ],

    'nutrition' => [
        'title' => 'Calorias & macros',
        'bmr' => '<strong>BMR (taxa metabólica basal):</strong> as calorias que o teu corpo gasta em repouso total, só para se manter vivo. É usada a Mifflin-St Jeor, ou a Katch-McArdle quando a tua gordura corporal é conhecida — mais rigorosa para quem é seco.',
        'tdee' => '<strong>TDEE / manutenção:</strong> o total de calorias que gastas num dia (BMR × o teu nível de atividade, mais treino). Come isto para manteres o peso.',
        'pal' => '<strong>Nível de atividade (PAL):</strong> um multiplicador para o quão ativo és, de 1,2 (sedentário) a 1,9 (muito ativo). Define-o no teu perfil.',
        'target' => '<strong>Calorias alvo:</strong> a manutenção ajustada ao teu objetivo — por exemplo +7,5% para um ganho limpo, −20% para um défice.',
        'macros' => '<strong>Proteína / gordura / hidratos:</strong> a proteína (cerca de 1,6 a 2,2 g/kg) constrói e protege músculo; a gordura (pelo menos 0,5 g/kg) suporta as hormonas; os hidratos alimentam o treino e preenchem as calorias restantes.',
        'adaptive' => '<strong>Manutenção adaptativa:</strong> assim que registares alguma comida e peso, a tua manutenção <em>real</em> é calculada a partir de como o teu peso se moveu de facto, e os teus alvos são ajustados — porque uma fórmula é só uma estimativa de partida.',
    ],

    'projections' => [
        'title' => 'Projeções',
        'lead' => 'É ajustada uma <strong>reta de tendência</strong> aos teus dados recentes e prolongada a um mês, trimestre, semestre e ano. São <strong>estimativas do género "se continuares assim", não promessas.</strong>',
        'r2' => '<strong>R² (qualidade):</strong> o quão bem a reta assenta nos teus dados, de 0 a 1. Perto de 1 é uma tendência limpa e fiável; perto de 0 significa dados ruidosos, e a projeção deve ser lida com cautela.',
        'dampened' => '<strong>Atenuada:</strong> os horizontes mais longos são reduzidos, porque progresso a continuar em linha reta durante um ano seria invulgar. A redução depende apenas de quão longe a estimativa chega — não é um modelo do teu teto pessoal.',
    ],

    'balance' => [
        'title' => 'Equilíbrio muscular',
        'lead' => 'Compara o volume de treino entre zonas opostas e relacionadas, para que te desenvolvas de forma equilibrada e reduzas o risco de lesão:',
        'push_pull' => '<strong>Empurrar contra puxar</strong> (peito, ombros e tríceps contra costas e bíceps)',
        'quads_posterior' => '<strong>Quadríceps contra cadeia posterior</strong> (frente da coxa contra isquiotibiais, glúteos e lombar)',
        'upper_lower' => '<strong>Trem superior contra trem inferior</strong>',
        'ratio' => 'Um rácio perto de <strong>1,0</strong> é saudável — 0,8 a 1,25 é considerado equilibrado. Bastante longe de 1,0 significa que um dos lados está a levar muito mais trabalho, o que é uma causa comum de estagnação, problemas de postura e lesões.',
    ],

    'sources' => [
        'title' => 'Fontes',
        'schoenfeld' => 'Schoenfeld et al. — relação dose-resposta entre séries semanais e hipertrofia.',
        'rp' => 'Renaissance Periodization (Israetel et al.) — landmarks de volume MV/MEV/MAV/MRV.',
        'epley' => 'Epley (1985) e Brzycki (1998) — fórmulas de estimativa de 1RM.',
        'mifflin' => 'Mifflin-St Jeor (1990), Katch-McArdle/Cunningham — BMR e gasto energético.',
        'helms' => 'Helms, Aragon, Morton et al. — ingestão de proteína e ritmo de ganho magro.',
        'kouri' => 'Kouri et al. — FFMI e o teto muscular natural.',
        'disclaimer' => 'Apenas educativo — não é aconselhamento médico.',
    ],

];
