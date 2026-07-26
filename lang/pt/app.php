<?php

/*
 | Português europeu (pt_PT).
 |
 | Termos de treino mantidos como são usados no ginásio em Portugal: "séries"
 | (não "conjuntos"), "carga", "massa magra". Onde o termo corrente é o inglês —
 | como "hipertrofia" ou os nomes das métricas — mantém-se o inglês, porque
 | traduzir à força torna o texto menos claro para quem treina.
 */

return [

    'brand' => 'Hevy Analytics',

    'nav' => [
        'dashboard' => 'Painel',
        'performance' => 'Desempenho',
        'levels' => 'Níveis',
        'muscle' => 'Músculo',
        'body' => 'Corpo',
        'photos' => 'Fotografias',
        'nutrition' => 'Nutrição',
        'projections' => 'Projeções',
        'routines' => 'Rotinas',
        'goals' => 'Objetivos',
        'ai' => 'IA',
        'guide' => 'Guia',
        'sync' => 'Sincronizar',
        'profile' => 'Perfil e definições',
        'write_operations' => 'Operações de escrita',
        'log_out' => 'Terminar sessão',
        'toggle' => 'Alternar navegação',
        'primary' => 'Principal',
        'language' => 'Idioma',
    ],

    'sync' => [
        'queued' => 'Sincronização em fila — atualize daqui a pouco para ver os novos dados.',
        'running' => 'A sincronizar os seus dados do Hevy…',
        'stalled' => 'A sincronização está em fila mas ninguém a processou. Se está a correr esta aplicação por si próprio, inicie um worker: php artisan queue:work',
        'failed' => 'A última sincronização falhou: :error',
        'needs_key' => 'Adicione primeiro a sua chave da API do Hevy no Perfil.',
        'last_synced' => 'Última sincronização: :when',
        'never' => 'nunca',
    ],

    'dashboard' => [
        'title' => 'Painel',
        'welcome_title' => 'Bem-vindo ao Hevy Analytics',
        'welcome_body' => 'Para começar, adicione a sua chave da API do Hevy e o seu perfil corporal, depois sincronize os dados.',
        'set_up_profile' => 'Configurar perfil',
        'workouts' => ':count treinos',
        'goal' => 'Objetivo',
        'no_goal' => 'Sem objetivo definido',
        'weight' => 'Peso',
        'body_fat' => 'Massa gorda',
        'lean_mass' => 'Massa magra',
        'volume_4wk' => 'Volume (4 sem.)',
        'hard_sets_4wk' => 'Séries efetivas (4 sem.)',
        'training_volume' => 'Volume de treino',
        'training_volume_sub' => 'Tonelagem semanal, últimos 6 meses',
        'body_composition' => 'Composição corporal',
        'body_composition_sub' => 'Peso vs massa magra, últimos 12 meses',
        'weekly_sets' => 'Séries semanais por músculo',
        'weekly_sets_sub' => 'vs referências de hipertrofia',
        'muscle_balance' => 'Equilíbrio muscular',
        'whats_next' => 'O que se segue',
        'at_a_glance' => 'Num relance',
        'trends' => 'Tendências',
        'detail' => 'Detalhe',
        'more_alerts' => 'mais :count',
        'nutrition_targets' => 'Metas nutricionais',
        'nutrition_targets_sub' => 'Diárias, com base no seu objetivo',
        'no_training_yet' => 'Ainda sem treinos registados',
        'no_training_yet_body' => 'Sincronize a sua conta Hevy e as séries semanais por músculo aparecem aqui.',
        'muscle_balance_sub' => 'Rácio de séries, 3 meses',
    ],

    'nutrition' => [
        'recompute' => 'Recalcular alvos',
        'maintenance' => 'Manutenção (TDEE)',
        'target_calories' => 'Calorias-alvo',
        'protein' => 'Proteína',
        'fat' => 'Gordura',
        'carbs' => 'Hidratos',
    ],

    'chart' => [
        'no_data' => 'Sem dados neste intervalo.',
        'no_body_data' => 'Registe uma medição de peso para ver este gráfico.',
        'no_tape_data' => 'Registe uma medição com fita métrica para ver este gráfico.',
        'no_sets' => 'Ainda não há séries registadas neste intervalo.',
        'description' => 'Gráfico :type com :series, de :from a :to',
    ],

    'balance' => [
        'not_enough' => 'ainda sem dados suficientes',
        'balanced' => 'equilibrado',
        'skewed' => 'desequilibrado',
        'unit' => 'séries/sem.',
    ],

    'profile' => [
        'timezone' => 'Fuso horário',
        'timezone_help' => 'Define a que dia e semana pertence cada treino. Se estiver errado, um treino ao fim do dia pode cair na semana anterior e distorcer as séries por semana.',
        'language' => 'Idioma',
        'language_help' => 'O idioma em que a aplicação é apresentada. Os seus dados de treino nunca são traduzidos.',
        'follow_browser' => 'Seguir o meu navegador',
    ],

    'ai' => [
        'generate' => 'Gerar análise',
        'regenerate' => 'Gerar de novo',
        'unavailable' => 'A análise por IA ainda não está disponível na sua conta.',
        'quota' => 'Restam :remaining de :limit análises este mês.',
        'quota_spent' => 'Usou as :limit análises por IA deste mês. O limite volta a zero a :date.',
        'temporarily_unavailable' => 'A análise por IA está temporariamente indisponível enquanto reforçamos a capacidade. Tente novamente mais tarde.',
        'unchanged' => 'A mostrar a análise mais recente — os seus dados não mudaram desde que foi gerada.',
    ],

    'routines' => [
        'edit_progression' => 'Editar / preparar progressão →',
    ],

    'write' => [
        'confirm' => 'Confirmar e enviar',
        'retry' => 'Repetir',
        'stalled' => 'bloqueada',
        'details' => 'detalhes',
    ],

    'photos' => [
        'limit_reached' => 'Atingiu o limite de :limit fotografias de progresso. Elimine algumas antigas para adicionar mais.',
    ],

    'muscles' => [
        'chest' => 'Peito',
        'shoulders' => 'Ombros',
        'triceps' => 'Tríceps',
        'biceps' => 'Bíceps',
        'forearms' => 'Antebraços',
        'lats' => 'Dorsais',
        'upper_back' => 'Costas superiores',
        'lower_back' => 'Lombar',
        'traps' => 'Trapézios',
        'abdominals' => 'Abdominais',
        'quadriceps' => 'Quadricípites',
        'hamstrings' => 'Isquiotibiais',
        'glutes' => 'Glúteos',
        'calves' => 'Gémeos',
        'abductors' => 'Abdutores',
        'adductors' => 'Adutores',
        'neck' => 'Pescoço',
        'cardio' => 'Cardio',
        'full_body' => 'Corpo inteiro',
        'other' => 'Outro',
    ],

    'volume_status' => [
        'below_maintenance' => 'Abaixo da manutenção',
        'maintenance' => 'Manutenção',
        'optimal' => 'Ótimo',
        'growth' => 'Crescimento',
        'junk' => 'Volume inútil',
        'per_week' => ':count/sem.',
    ],

    'balance_groups' => [
        'push' => 'Empurrar',
        'pull' => 'Puxar',
        'quads' => 'Quadricípites',
        'posterior' => 'Cadeia posterior',
        'upper' => 'Tronco superior',
        'lower' => 'Tronco inferior',
    ],

    'series' => [
        'weight' => 'Peso (kg)',
        'lean_mass' => 'Massa magra (kg)',
        'tonnage' => 'Tonelagem (kg)',
        'body_fat' => 'Massa gorda %',
        'ffmi' => 'FFMI',
        'chest' => 'Peito',
        'waist' => 'Cintura',
        'bicep' => 'Bíceps (D)',
        'e1rm' => 'e1RM (kg)',
    ],

    'alerts' => [
        'gaining_too_fast' => 'A ganhar demasiado depressa',
        'gaining_too_fast_body' => 'O peso sobe :observed%PC/semana face ao alvo de :target%. O excesso será provavelmente gordura — corte ~150-250 kcal.',
        'bulk_stalling' => 'Ganho estagnado',
        'bulk_stalling_body' => 'Apenas :observed%PC/semana ganhos (alvo :target%). Acrescente ~150-250 kcal para continuar a construir.',
        'on_track' => 'No bom caminho',
        'on_track_bulk_body' => 'A ganhar :observed%PC/semana — dentro da faixa de ganho limpo.',
        'cut_too_slow' => 'Défice demasiado lento',
        'cut_too_slow_body' => 'A perder apenas :observed%PC/semana (alvo :target%). Reduza ~200 kcal.',
        'cut_too_fast' => 'Défice demasiado agressivo',
        'cut_too_fast_body' => 'A perder :observed%PC/semana (alvo :target%) — risco de perda muscular. Aumente as calorias.',
        'on_track_cut_body' => 'A perder :observed%PC/semana — ritmo de défice sustentável.',
        'poor_partitioning' => 'Má repartição do ganho',
        'poor_partitioning_body' => 'Estima-se que ~:percent% do peso ganho recentemente foi massa magra, com base numa tendência de :readings medições.',
        'bia_caveat' => 'Nota: a massa gorda vem de uma balança BIA (ruidosa) — confirme com o espelho, a fita na cintura e a força.',
        'poor_partitioning_low' => 'Estimativa de repartição (baixa confiança)',
        'poor_partitioning_low_body' => 'Uma estimativa aproximada sugere ~:percent% de ganho magro, mas ainda não há dados consistentes que a sustentem.',
        'fat_climbing' => 'Massa gorda a subir',
        'fat_climbing_body' => 'A massa gorda subiu ~:points pontos recentemente. Vigie o tamanho do excedente.',
        'waist_high' => 'Cintura/altura acima de 0,5',
        'waist_high_body' => 'O rácio cintura/altura ultrapassa a referência de saúde de 0,5 — vigie a cintura durante o ganho.',
        'asymmetry' => 'Assimetria: :part',
        'asymmetry_body' => ':part difere :percent% entre esquerda e direita — considere trabalho unilateral.',
        'no_data' => 'Ainda sem dados suficientes',
        'no_data_body' => 'Registe mais algumas medições corporais ao longo do tempo para desbloquear alertas baseados em tendências.',
    ],

    'goals' => [
        'lean_bulk' => 'Ganho limpo',
        'hypertrophy' => 'Hipertrofia / manutenção com ganho',
        'aggressive_bulk' => 'Ganho agressivo',
        'recomposition' => 'Recomposição',
        'cut' => 'Défice / perda de gordura',
        'strength' => 'Força',
    ],

    'units' => [
        'bw_per_week' => ':value%PC/sem.',
        'per_week' => '/sem.',
    ],

    'theme' => [
        'system' => 'Acompanhar o sistema',
        'light' => 'Claro',
        'dark' => 'Escuro',
        'switch_dark' => 'Mudar para modo escuro',
        'switch_light' => 'Mudar para modo claro',
    ],

    'sections' => [
        'today' => 'Hoje',
        'today_question' => 'O que devo fazer esta semana?',
        'training' => 'Treino',
        'training_question' => 'Estou a ficar mais forte?',
        'body' => 'Corpo',
        'body_question' => 'O meu corpo está a mudar como quero?',
        'outlook' => 'Perspetiva',
        'outlook_question' => 'Onde é que isto vai dar?',
    ],

    'pages' => [
        'body' => 'Composição corporal',
        'body_sub' => 'Peso, massa gorda e massa magra — a tendência, não o ruído diário',
        'muscle' => 'Análise muscular',
        'muscle_sub' => 'Que músculos estão sub-treinados, e por quanto',
        'performance' => 'Desempenho ao longo do tempo',
        'performance_sub' => 'Se o peso na barra está mesmo a subir',
        'levels' => 'Níveis de força',
        'levels_sub' => 'Como os teus levantamentos comparam com atletas da tua idade, peso e sexo',
        'routines' => 'Rotinas',
        'routines_sub' => 'Que rotinas estão a progredir, e quais estagnaram',
        'routine_edit' => 'Editar: :name',
        'nutrition' => 'Nutrição',
        'nutrition_sub' => 'Alvos de calorias e macros derivados dos teus próprios dados',
        'photos' => 'Fotografias de progresso',
        'photos_sub' => 'A medição que uma balança não consegue fazer',
        'projections' => 'Projeções',
        'projections_sub' => 'Onde o teu ritmo atual leva — estimativas, não promessas',
        'goals' => 'Objetivos',
        'goals_sub' => 'Define o alvo com que todas as outras páginas te comparam',
        'ai' => 'Análise profunda com IA',
        'ai_sub' => 'Uma leitura escrita do teu treino e dados corporais recentes',
        'guide' => 'Guia',
        'guide_sub' => 'O que significa cada métrica deste site, e porque importa',
        'write' => 'Operações de escrita',
        'write_sub' => 'Alterações preparadas para o Hevy — revê o conteúdo antes de enviar',
        'profile' => 'Perfil e definições',
    ],

    'tips' => [
        'weight' => 'O seu peso corporal e a que ritmo muda por semana. Faixa ideal de ganho limpo: +0,25% a +0,5% do peso corporal por semana.',
        'body_fat' => 'Percentagem do seu peso que é gordura. Navy = uma estimativa independente com fita métrica, usada como verificação cruzada.',
        'lean_mass' => 'Tudo o que não é gordura (sobretudo músculo). FFMI = musculatura ajustada à altura; ~25 é perto do limite natural nos homens.',
        'hard_sets' => 'Total de séries efetivas (sem aquecimentos) nas últimas 4 semanas — o principal motor do crescimento muscular.',
        'landmarks' => 'Séries efetivas por músculo em cada semana. MEV = mínimo para crescer, MAV = zona de melhor retorno. Fique entre os dois. "Abaixo da manutenção" significa séries a menos para crescer.',
    ],

    'common' => [
        'save' => 'Guardar',
        'saved' => 'Guardado.',
        'apply' => 'Aplicar',
        'all' => 'Todos',
        'from' => 'De',
        'to' => 'Até',
        'period' => 'Período',
        'learn_more' => 'Saber mais →',
        'skip_to_content' => 'Saltar para o conteúdo',
        'more_info' => 'Mais informação',
    ],

];
