<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Database\Seeder;

/**
 * The Meta Ads rules the LLM reasons with. Keys are numbered so the alphabetical order
 * the repository sorts by is also the order these must be read in.
 */
class DomainKnowledgeSeeder extends Seeder
{
    private const string LOCALE = 'es';

    public function run(): void
    {
        foreach ($this->entries() as $key => $entry) {
            KnowledgeEntry::query()->firstOrCreate(
                [
                    'type' => KnowledgeType::DomainRule,
                    'key' => $key,
                    'locale' => self::LOCALE,
                    'version' => 1,
                ],
                $entry + ['is_published' => true],
            );
        }
    }

    private function entries(): array
    {
        return [
            'meta-ads-00-campaign-vs-organic' => [
                'title' => 'Campaña no es lo mismo que contenido orgánico',
                'body' => <<<'TEXT'
                    En Meta, la palabra «campaña» siempre implica dinero. Una campaña es la
                    estructura de tres niveles del Ads Manager: Campaña (define el objetivo)
                    → Conjunto de anuncios / Ad Set (define presupuesto, público, ubicaciones
                    y calendario) → Anuncio / Ad (define el creativo que la gente ve). Si no
                    hay presupuesto asignado, no hay campaña.

                    Un post orgánico es distinto: se publica en el perfil, es gratuito, lo ve
                    quien ya sigue la cuenta más el alcance que el algoritmo regale, y no
                    forma parte de ninguna estructura de Ads Manager.

                    El puente entre ambos mundos es el ID del post. Un post orgánico que ya
                    funcionó puede usarse como anuncio dentro de una campaña referenciando su
                    post ID. Al hacerlo, el anuncio hereda la prueba social acumulada — los
                    likes, comentarios y compartidos que el post ya tenía — en lugar de
                    arrancar desde cero. Es la forma más barata de convertir contenido
                    ganador en pauta.

                    En esta aplicación las dos cosas se modelan como Experimentos, y el campo
                    `type` los distingue: un experimento `organic` no lleva presupuesto y se
                    mide con métricas de alcance e interacción; un experimento `ads` lleva
                    presupuesto, se ejecuta contra la Marketing API y se mide con métricas de
                    costo y conversión. Confundir los dos lleva a evaluar un post orgánico con
                    la vara de un anuncio pagado, que es una comparación sin sentido.
                    TEXT,
                'metadata' => [
                    'ads_manager_levels' => ['campaign', 'ad_set', 'ad'],
                    'experiment_types' => ['organic', 'ads'],
                    'organic_reuse_mechanism' => 'post_id',
                ],
            ],

            'meta-ads-01-learning-phase' => [
                'title' => 'Fase de aprendizaje',
                'body' => <<<'TEXT'
                    Todo conjunto de anuncios (ad set) nuevo, o editado de forma
                    significativa, entra en fase de aprendizaje. Durante esa fase el
                    algoritmo de Meta está explorando: prueba distintas audiencias,
                    ubicaciones y momentos del día para descubrir a quién conviene mostrarle
                    el anuncio.

                    Meta necesita aproximadamente 50 eventos de optimización en una ventana
                    móvil de 7 días para salir de la fase de aprendizaje y estabilizar la
                    entrega. «Evento de optimización» es el evento por el que el ad set está
                    optimizando: si optimiza por compras, son 50 compras; si optimiza por
                    leads, 50 leads. La ventana es móvil, no acumulativa desde el inicio: lo
                    que cuenta son los eventos de los últimos 7 días en cualquier momento
                    dado.

                    Durante la fase de aprendizaje los costos son más altos y mucho más
                    volátiles que después. Un CPA que se dispara el día 2 y se desploma el
                    día 4 es el comportamiento esperado, no la señal de una campaña rota. Por
                    eso no se debe juzgar el rendimiento en los primeros días ni tomar
                    decisiones de pausa o de escalado basadas en esos números.

                    Consecuencia operativa en esta aplicación: el guardián no propone pausas
                    por rendimiento mientras el experimento está dentro de su ventana de
                    aprendizaje. Solo actúa por desastres de entrega o de gasto — el anuncio
                    no se entrega en absoluto, o el gasto se dispara muy por encima del tope.
                    TEXT,
                'metadata' => [
                    'events_needed' => 50,
                    'window_days' => 7,
                    'window_type' => 'rolling',
                    'guardian_pauses_on_performance' => false,
                ],
            ],

            'meta-ads-02-edits-reset-learning' => [
                'title' => 'Las ediciones resetean el aprendizaje',
                'body' => <<<'TEXT'
                    Cada cambio significativo sobre un conjunto de anuncios lo devuelve al
                    inicio de la fase de aprendizaje. El contador de 50 eventos vuelve a
                    cero, y con él vuelven los costos altos y volátiles.

                    Cuentan como cambio significativo: modificar el presupuesto en más de
                    aproximadamente un 20 %, cambiar el targeting (públicos, ubicaciones
                    geográficas, edades, intereses), cambiar los creativos (imagen, video,
                    copy principal) y cambiar la estrategia o el monto de puja.

                    El error típico es editar cada pocos días buscando mejorar. El resultado
                    es una fase de aprendizaje permanente: el ad set nunca llega a
                    estabilizarse y sus costos nunca bajan al nivel que alcanzaría si lo
                    dejaran en paz. Se paga el precio de la exploración una y otra vez sin
                    cobrar nunca el beneficio de la explotación.

                    La práctica correcta es agrupar los cambios en lotes: acumular todo lo
                    que se quiere ajustar, aplicarlo de una sola vez, y después no tocar
                    nada durante una o dos semanas. Si hay que cambiar el presupuesto,
                    hacerlo en un solo movimiento en lugar de en tres incrementos pequeños.

                    Consecuencia operativa en esta aplicación: antes de aceptar cualquier
                    propuesta que resetee el aprendizaje, la app advierte explícitamente. Y
                    si el usuario intenta la misma acción de forma manual sobre un
                    experimento que está dentro de su ventana de aprendizaje, un modal
                    contextual le muestra las fechas de la ventana y por qué la volatilidad
                    actual es esperada. Nunca se le bloquea: se le informa para que decida.
                    TEXT,
                'metadata' => [
                    'significant_budget_change_percent' => 20,
                    'reset_triggers' => ['budget', 'targeting', 'creative', 'bid'],
                    'recommended_freeze_days_after_launch' => 14,
                    'minimum_freeze_days_after_launch' => 7,
                ],
            ],

            'meta-ads-03-minimum-budget' => [
                'title' => 'Presupuesto mínimo matemático',
                'body' => <<<'TEXT'
                    De la regla de los 50 eventos en 7 días se deduce un presupuesto diario
                    mínimo. Si hacen falta 50 eventos por semana y cada evento cuesta el CPA
                    objetivo, entonces:

                    presupuesto diario mínimo ≈ (CPA objetivo × 50) ÷ 7

                    Ejemplo: con un CPA objetivo de 60 USD, el mínimo es (60 × 50) ÷ 7 ≈ 429
                    USD por día. No es una recomendación de crecimiento: es el suelo por
                    debajo del cual el conjunto de anuncios no puede generar los eventos que
                    necesita para aprender.

                    Por debajo de ese umbral el ad set queda en estado «Learning Limited»
                    (aprendizaje limitado): nunca acumula los 50 eventos, nunca sale de la
                    fase de aprendizaje, y arrastra costos altos de forma indefinida. Gastar
                    poco no sale barato: sale caro por unidad.

                    Cuando el presupuesto disponible no alcanza, hay dos salidas legítimas.
                    La primera es subir el presupuesto hasta el mínimo, aunque signifique
                    correr un solo experimento en lugar de tres. La segunda es optimizar por
                    un evento más frecuente y más barato del embudo — por ejemplo «Añadir al
                    carrito» en lugar de «Compra», o «Ver contenido» en lugar de «Lead» —
                    porque baja el CPA objetivo y con él el mínimo requerido.

                    Consecuencia operativa en esta aplicación: al crear un experimento de
                    tipo `ads` se valida esta fórmula contra el presupuesto propuesto y, si
                    no se cumple, se adjunta al experimento una advertencia con el monto
                    calculado y las dos alternativas.
                    TEXT,
                'metadata' => [
                    'budget_formula' => '(cpa * 50) / 7',
                    'events_needed' => 50,
                    'window_days' => 7,
                    'below_minimum_state' => 'Learning Limited',
                    'alternatives' => ['raise_budget', 'optimize_for_cheaper_funnel_event'],
                ],
            ],

            'meta-ads-04-consolidate-over-fragment' => [
                'title' => 'Consolidar es mejor que fragmentar',
                'body' => <<<'TEXT'
                    Pocos conjuntos de anuncios grandes rinden mejor que muchos pequeños,
                    porque cada ad set tiene su propia fase de aprendizaje y su propio
                    contador de 50 eventos.

                    La aritmética es directa: 5 conjuntos de 20 USD diarios son 5 fases de
                    aprendizaje hambrientas, cada una peleando por llegar a 50 eventos con
                    una quinta parte del presupuesto. Un solo conjunto de 100 USD diarios
                    concentra todos los eventos en un único contador y sale de la fase de
                    aprendizaje aproximadamente cinco veces más rápido. Además evita que los
                    propios ad sets compitan entre sí en la subasta por la misma audiencia,
                    encareciendo el CPM de todos.

                    La estructura moderna recomendada es: una campaña por objetivo de
                    negocio, entre 2 y 4 conjuntos de anuncios diferenciados por concepto
                    creativo (no por micro-segmentos de audiencia), y targeting amplio o
                    Advantage+ dejando que el algoritmo encuentre a quién mostrarle cada
                    creativo.

                    La tentación de fragmentar viene de la práctica antigua de segmentar por
                    intereses. Con el algoritmo actual esa segmentación manual aporta poco y
                    cuesta mucho en velocidad de aprendizaje.
                    TEXT,
                'metadata' => [
                    'recommended_ad_sets_min' => 2,
                    'recommended_ad_sets_max' => 4,
                    'differentiate_by' => 'creative_concept',
                    'recommended_targeting' => ['broad', 'advantage_plus'],
                ],
            ],

            'meta-ads-05-creative-is-targeting' => [
                'title' => 'El creativo es el targeting',
                'body' => <<<'TEXT'
                    Con el algoritmo Andromeda (2025-2026), las señales del creativo pesan
                    más que los intereses configurados a la hora de decidir a quién se le
                    muestra un anuncio. Meta lee cómo reacciona la gente al creativo — hook
                    rate en los primeros segundos, retención a lo largo del video,
                    interacción — y usa esas señales para encontrar audiencias parecidas a
                    quienes ya respondieron bien.

                    En la práctica esto invierte la prioridad clásica. La diversidad
                    creativa rinde más que la microsegmentación manual: es mejor probar
                    cuatro conceptos creativos distintos contra un público amplio que un
                    mismo creativo contra cuatro públicos estrechos. El público lo encuentra
                    el algoritmo; lo que no puede inventar es el creativo.

                    Esto conecta directamente los dos módulos de esta aplicación. El
                    contenido orgánico que ya funcionó es la mejor materia prima para
                    anuncios: sus señales creativas están medidas, no supuestas. Usar el
                    post ID de un orgánico ganador como anuncio aprovecha además la prueba
                    social ya acumulada (véase la regla sobre campaña y contenido orgánico).
                    El Content Planner produce la evidencia; el Campaign Manager la compra.
                    TEXT,
                'metadata' => [
                    'algorithm' => 'Andromeda',
                    'creative_signals' => ['hook_rate', 'retention', 'engagement'],
                    'preferred_strategy' => 'creative_diversity_over_microtargeting',
                ],
            ],

            'meta-ads-06-minimum-duration' => [
                'title' => 'Duración mínima de un experimento de ads',
                'body' => <<<'TEXT'
                    Un experimento de tipo `ads` necesita al menos 7 días desde el último
                    cambio significativo antes de poder evaluarse, e idealmente 14.

                    El motivo es la fase de aprendizaje: la ventana de los 50 eventos es de
                    7 días, así que antes de que se cumplan no existe todavía un
                    comportamiento estable que medir. Lo que se observa en los días 1 a 5 es
                    la exploración del algoritmo, no el rendimiento del anuncio. Evaluar
                    antes es evaluar ruido, y las decisiones tomadas sobre ruido — pausar lo
                    que iba a funcionar, escalar lo que fue un pico casual — son peores que
                    no tomar ninguna.

                    Los 7 días se cuentan desde el último cambio significativo, no desde la
                    creación: si el presupuesto se editó en el día 4, el reloj vuelve a
                    empezar ahí.

                    Catorce días es el ideal porque cubre dos ciclos semanales completos y
                    absorbe la estacionalidad de los días de semana frente al fin de semana,
                    que en muchos negocios mueve el CPA más que cualquier ajuste de
                    configuración.

                    Consecuencia operativa en esta aplicación: al crear un experimento de
                    ads con una duración menor a 7 días se adjunta una advertencia con la
                    fecha mínima de evaluación calculada, y cerrar el experimento antes de
                    esa fecha lo marca como cerrado anticipadamente.
                    TEXT,
                'metadata' => [
                    'minimum_duration_days' => 7,
                    'recommended_duration_days' => 14,
                    'counted_from' => 'last_significant_change',
                ],
            ],
        ];
    }
}
