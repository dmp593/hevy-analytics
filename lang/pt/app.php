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
        'muscle_balance_sub' => 'Rácio de séries, 3 meses',
    ],

    'nutrition' => [
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
        'unavailable' => 'A análise por IA ainda não está disponível na sua conta.',
        'quota' => 'Restam :remaining de :limit análises este mês.',
        'quota_spent' => 'Usou as :limit análises por IA deste mês. O limite volta a zero a :date.',
        'temporarily_unavailable' => 'A análise por IA está temporariamente indisponível enquanto reforçamos a capacidade. Tente novamente mais tarde.',
        'unchanged' => 'A mostrar a análise mais recente — os seus dados não mudaram desde que foi gerada.',
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

    'common' => [
        'save' => 'Guardar',
        'saved' => 'Guardado.',
        'apply' => 'Aplicar',
        'all' => 'Todos',
        'from' => 'De',
        'to' => 'Até',
        'period' => 'Período',
        'learn_more' => 'Saber mais →',
        'more_info' => 'Mais informação',
    ],

];
