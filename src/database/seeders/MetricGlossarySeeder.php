<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Database\Seeder;

/**
 * The single source of truth behind every <Term> tooltip in the UI. `metadata` carries the
 * structured half so the component never has to parse the prose.
 */
class MetricGlossarySeeder extends Seeder
{
    private const string LOCALE = 'es';

    public function run(): void
    {
        foreach ($this->entries() as $key => $entry) {
            KnowledgeEntry::query()->firstOrCreate(
                [
                    'type' => KnowledgeType::GlossaryTerm,
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
            'impressions' => [
                'title' => 'Impresiones',
                'body' => <<<'TEXT'
                    **Qué es.** El número de veces que una pieza se mostró en pantalla. Si la
                    misma persona ve el anuncio cuatro veces, son cuatro impresiones.

                    **Cómo se calcula.** Es un conteo directo que reporta la plataforma. Se
                    relaciona con el alcance así: impresiones = alcance × frecuencia.

                    **Qué valores son buenos.** No hay bueno ni malo en abstracto. Las
                    impresiones miden volumen de entrega, no calidad: son el denominador de
                    casi todo lo demás (CPM, CTR, hook rate). Sirven para saber si la
                    campaña está entregando; si están cerca de cero con presupuesto activo,
                    hay un problema de entrega, no de rendimiento.

                    **Cuándo no fiarse.** Nunca uses impresiones como métrica de éxito. Subir
                    el presupuesto sube las impresiones por definición, y eso no significa
                    que nada mejore. Tampoco las compares entre plataformas: cada una cuenta
                    la impresión con reglas distintas.
                    TEXT,
                'metadata' => [
                    'formula' => 'conteo de veces que la pieza se mostró en pantalla',
                    'unit' => 'count',
                    'good_when' => 'higher',
                ],
            ],

            'reach' => [
                'title' => 'Alcance',
                'body' => <<<'TEXT'
                    **Qué es.** El número de personas únicas que vieron la pieza al menos una
                    vez. A diferencia de las impresiones, cada persona cuenta una sola vez.

                    **Cómo se calcula.** alcance = impresiones ÷ frecuencia. La plataforma lo
                    reporta directamente y lo deduplica por usuario dentro del período
                    consultado.

                    **Qué valores son buenos.** En objetivos de notoriedad y crecimiento de
                    audiencia, más alcance es mejor. En objetivos de conversión el alcance
                    importa menos que la calidad de a quién se alcanzó.

                    **Cuándo no fiarse.** El alcance no es sumable entre períodos ni entre
                    plataformas: el alcance de dos semanas no es la suma del alcance de cada
                    semana, porque las mismas personas se repiten. Y un alcance alto con
                    engagement bajo suele indicar que se está alcanzando a gente equivocada.
                    TEXT,
                'metadata' => [
                    'formula' => 'personas únicas alcanzadas = impresiones / frecuencia',
                    'unit' => 'count',
                    'good_when' => 'higher',
                ],
            ],

            'cpm' => [
                'title' => 'CPM — costo por mil impresiones',
                'body' => <<<'TEXT'
                    **Qué es.** Lo que cuesta que la plataforma muestre el anuncio mil veces.
                    Es el precio de la subasta, no el precio del resultado.

                    **Cómo se calcula.** CPM = (gasto ÷ impresiones) × 1000.

                    **Qué valores son buenos.** Depende por completo del país, la industria,
                    la época del año y la audiencia. Un CPM que en un mercado es barato en
                    otro es carísimo. Lo único comparable es tu propio CPM histórico en el
                    mismo mercado y con el mismo objetivo. Sube de forma previsible en
                    noviembre y diciembre porque compites con el retail.

                    **Cuándo no fiarse.** Un CPM bajo no es un logro si el público alcanzado
                    no convierte: se paga poco por llegar a quien no interesa. Optimizar
                    hacia CPM bajo empuja hacia ubicaciones y audiencias baratas y suele
                    empeorar el CPA. Es una métrica de diagnóstico, nunca un objetivo.
                    TEXT,
                'metadata' => [
                    'formula' => '(gasto / impresiones) * 1000',
                    'unit' => 'currency',
                    'good_when' => 'lower',
                ],
            ],

            'ctr' => [
                'title' => 'CTR — tasa de clics',
                'body' => <<<'TEXT'
                    **Qué es.** El porcentaje de impresiones que terminaron en un clic. Mide
                    cuánto interés genera el creativo frente a quien lo ve.

                    **Cómo se calcula.** CTR = (clics ÷ impresiones) × 100.

                    **Qué valores son buenos.** Distingue siempre el CTR total del CTR de
                    enlace saliente: el primero incluye clics en el perfil, en «ver más» y en
                    reacciones, y siempre parece mejor. Como referencia amplia en
                    Instagram/Facebook, un CTR de enlace por debajo de 0,5 % es flojo,
                    alrededor de 1 % es normal y por encima de 2 % es bueno. Varía mucho por
                    industria y por formato.

                    **Cuándo no fiarse.** Un CTR alto con conversiones bajas es la firma del
                    clickbait: el creativo promete algo que la landing no cumple. Y con pocas
                    impresiones el CTR es puro ruido estadístico — 2 clics en 50 impresiones
                    son un 4 % que no significa nada.
                    TEXT,
                'metadata' => [
                    'formula' => '(clics / impresiones) * 100',
                    'unit' => 'percent',
                    'good_when' => 'higher',
                ],
            ],

            'hook_rate' => [
                'title' => 'Hook rate — tasa de enganche',
                'body' => <<<'TEXT'
                    **Qué es.** El porcentaje de personas que no pasaron de largo en los
                    primeros segundos de un video. Mide exclusivamente la fuerza del arranque:
                    la primera imagen, la primera frase, el primer movimiento.

                    **Cómo se calcula.** hook rate = (reproducciones de 3 segundos ÷
                    impresiones) × 100.

                    **Qué valores son buenos.** En video vertical de Instagram y Facebook, por
                    debajo de 20 % el arranque no funciona, alrededor de 30 % es aceptable y
                    por encima de 40 % es fuerte. Es la métrica que más rápido diagnostica un
                    creativo: si el hook rate es bajo, nada de lo que venga después importa
                    porque casi nadie lo verá.

                    **Cuándo no fiarse.** Un hook rate excelente con retención pésima indica
                    un arranque llamativo que no sostiene la promesa; se está comprando
                    atención que se pierde a los cinco segundos. Compáralo siempre junto a la
                    retención, nunca solo.
                    TEXT,
                'metadata' => [
                    'formula' => '(reproducciones de 3 s / impresiones) * 100',
                    'unit' => 'percent',
                    'good_when' => 'higher',
                ],
            ],

            'retention' => [
                'title' => 'Retención',
                'body' => <<<'TEXT'
                    **Qué es.** Qué proporción del video llega a ver la gente que empezó a
                    verlo. Se reporta por hitos (25 %, 50 %, 75 %, 100 %) o como porcentaje
                    medio reproducido.

                    **Cómo se calcula.** retención al X % = (reproducciones que llegaron al
                    X % ÷ reproducciones iniciadas) × 100.

                    **Qué valores son buenos.** Depende de la duración: retener el 50 % de un
                    video de 15 segundos es normal; retener el 50 % de uno de 3 minutos es
                    excelente. Lo accionable es la curva, no el número: el punto exacto donde
                    cae en picado señala el segundo que hay que reescribir.

                    **Cuándo no fiarse.** Con muy pocas reproducciones iniciadas la curva es
                    ruido. Y la retención de un video corto no es comparable con la de uno
                    largo, ni la de un formato con la de otro — solo se compara contra
                    videos de duración y formato similares.
                    TEXT,
                'metadata' => [
                    'formula' => '(reproducciones que llegaron al hito / reproducciones iniciadas) * 100',
                    'unit' => 'percent',
                    'good_when' => 'higher',
                ],
            ],

            'engagement_rate' => [
                'title' => 'Engagement rate — tasa de interacción',
                'body' => <<<'TEXT'
                    **Qué es.** Qué proporción de la gente que vio la pieza hizo algo con
                    ella: reaccionar, comentar, guardar, compartir.

                    **Cómo se calcula.** En esta aplicación se calcula sobre alcance:
                    engagement rate = (interacciones ÷ alcance) × 100. Hay dos variantes muy
                    extendidas — sobre impresiones y sobre seguidores — que dan números
                    distintos; verifica siempre cuál está usando la fuente que compares.

                    **Qué valores son buenos.** En Instagram orgánico, por debajo de 1 % es
                    flojo, entre 1 % y 3 % es normal y por encima de 6 % es muy bueno. Baja de
                    forma natural a medida que crece la cuenta, así que la comparación válida
                    es contra tu propia media reciente.

                    **Cuándo no fiarse.** Trata los guardados y compartidos aparte: pesan
                    mucho más que un like para el alcance futuro, y una tasa agregada los
                    diluye. En contenido pagado, además, el engagement puede venir de gente
                    que nunca comprará: no lo confundas con intención de compra.
                    TEXT,
                'metadata' => [
                    'formula' => '(interacciones / alcance) * 100',
                    'unit' => 'percent',
                    'good_when' => 'higher',
                ],
            ],

            'cpc' => [
                'title' => 'CPC — costo por clic',
                'body' => <<<'TEXT'
                    **Qué es.** Lo que cuesta cada clic. Es el puente entre lo que se paga
                    por mostrar (CPM) y lo que se paga por resultado (CPA).

                    **Cómo se calcula.** CPC = gasto ÷ clics. También se deduce de las otras
                    dos: CPC = CPM ÷ (CTR × 10).

                    **Qué valores son buenos.** Igual que el CPM, solo tiene sentido contra tu
                    propio histórico en el mismo mercado y objetivo. Un CPC que sube mientras
                    el CPM se mantiene estable significa que el creativo perdió fuerza, no
                    que la subasta se encareció — es una señal temprana y útil.

                    **Cuándo no fiarse.** Distingue el CPC total del CPC de enlace saliente:
                    el primero es siempre más barato y más halagador. Y un CPC bajo no
                    justifica nada si esos clics no convierten; el CPC barato de tráfico
                    curioso es la forma más común de gastar bien y vender mal.
                    TEXT,
                'metadata' => [
                    'formula' => 'gasto / clics',
                    'unit' => 'currency',
                    'good_when' => 'lower',
                ],
            ],

            'cpa' => [
                'title' => 'CPA — costo por adquisición',
                'body' => <<<'TEXT'
                    **Qué es.** Lo que cuesta conseguir una conversión: una compra, un
                    registro, la acción por la que el conjunto de anuncios está optimizando.

                    **Cómo se calcula.** CPA = gasto ÷ conversiones.

                    **Qué valores son buenos.** El único criterio que importa es el margen: el
                    CPA es bueno si es menor que lo que deja esa conversión. Un CPA de 5 USD
                    puede ser ruinoso y uno de 300 USD puede ser excelente, según el ticket y
                    el valor de vida del cliente. Este número, además, es el que alimenta la
                    fórmula del presupuesto diario mínimo: (CPA objetivo × 50) ÷ 7.

                    **Cuándo no fiarse.** Es la métrica en la que más caro sale equivocarse.
                    No la mires durante la fase de aprendizaje: mientras el ad set no lleva 50
                    eventos en 7 días, el CPA es volátil por diseño y pausar por un CPA malo
                    del día 2 es la forma clásica de matar una campaña que iba bien. Tampoco
                    la mires con menos de ~10 conversiones acumuladas, y ten presente que la
                    atribución de Meta reparte el mérito con criterios propios: su CPA y el de
                    tu contabilidad rara vez coinciden.
                    TEXT,
                'metadata' => [
                    'formula' => 'gasto / conversiones',
                    'unit' => 'currency',
                    'good_when' => 'lower',
                    'unreliable_during_learning_phase' => true,
                ],
            ],

            'cpl' => [
                'title' => 'CPL — costo por lead',
                'body' => <<<'TEXT'
                    **Qué es.** El CPA cuando la conversión es un lead: un formulario
                    enviado, un contacto dejado, una solicitud de demostración.

                    **Cómo se calcula.** CPL = gasto ÷ leads.

                    **Qué valores son buenos.** Se juzga contra la tasa de cierre: si de cada
                    20 leads se cierra uno y ese cierre deja 1.000 USD, un CPL de hasta 50
                    USD es rentable. Sin la tasa de cierre, el CPL solo no dice nada.

                    **Cuándo no fiarse.** Es la métrica más fácil de mejorar empeorando el
                    negocio. Un formulario más corto y un anuncio más vago bajan el CPL y
                    llenan el embudo de leads que no cierran. Mira siempre el CPL junto al
                    costo por lead cualificado. Y como cualquier CPA, no lo evalúes durante la
                    fase de aprendizaje.
                    TEXT,
                'metadata' => [
                    'formula' => 'gasto / leads',
                    'unit' => 'currency',
                    'good_when' => 'lower',
                    'unreliable_during_learning_phase' => true,
                ],
            ],

            'roas' => [
                'title' => 'ROAS — retorno del gasto publicitario',
                'body' => <<<'TEXT'
                    **Qué es.** Cuántos ingresos genera cada unidad de moneda gastada en
                    publicidad. Un ROAS de 3 significa 3 USD de ingreso por cada USD gastado.

                    **Cómo se calcula.** ROAS = ingresos atribuidos ÷ gasto publicitario.

                    **Qué valores son buenos.** Depende del margen bruto. El punto de
                    equilibrio es 1 ÷ margen: con un margen del 30 %, un ROAS de 3,3 solo
                    empata; con un margen del 70 %, un ROAS de 1,5 ya deja ganancia. Solo
                    tiene sentido para negocios con ingreso medible por transacción.

                    **Cuándo no fiarse.** ROAS no es beneficio: son ingresos, sin descontar
                    costo de producto, envíos, devoluciones ni el trabajo propio. Depende
                    además de la ventana de atribución configurada — cambiar de 7 días a 1 día
                    puede reducirlo a la mitad sin que nada haya cambiado en la realidad. Y en
                    campañas de remarketing suele estar inflado, porque atribuye ventas que
                    habrían ocurrido igual.
                    TEXT,
                'metadata' => [
                    'formula' => 'ingresos atribuidos / gasto',
                    'unit' => 'ratio',
                    'good_when' => 'higher',
                ],
            ],

            'frequency' => [
                'title' => 'Frecuencia',
                'body' => <<<'TEXT'
                    **Qué es.** Cuántas veces vio el anuncio, en promedio, cada persona
                    alcanzada.

                    **Cómo se calcula.** frecuencia = impresiones ÷ alcance.

                    **Qué valores son buenos.** No es «cuanto más baja mejor»: hace falta
                    repetición para que el mensaje cale. En una campaña de conversión de dos
                    semanas, entre 1,5 y 3 es una zona sana. Por encima de 4 aparece la fatiga
                    creativa: el CTR baja, el CPM sube y el CPA se deteriora sin que nada haya
                    cambiado en la configuración.

                    **Cuándo no fiarse.** Es un promedio y esconde la distribución: una
                    frecuencia media de 2 puede significar que la mitad vio el anuncio una vez
                    y la otra mitad tres. Solo es comparable dentro de un mismo período — la
                    frecuencia de un mes siempre parece más alta que la de una semana. Con
                    audiencias muy pequeñas sube rápido por definición y no indica fatiga sino
                    falta de gente a quien alcanzar.
                    TEXT,
                'metadata' => [
                    'formula' => 'impresiones / alcance',
                    'unit' => 'ratio',
                    'good_when' => 'lower',
                    'healthy_range' => [1.5, 3.0],
                    'fatigue_threshold' => 4.0,
                ],
            ],

            'conversions' => [
                'title' => 'Conversiones',
                'body' => <<<'TEXT'
                    **Qué es.** El número de veces que ocurrió la acción por la que el
                    conjunto de anuncios optimiza: compras, registros, leads, añadidos al
                    carrito. Es el evento de optimización de la fase de aprendizaje.

                    **Cómo se calcula.** Es un conteo de eventos atribuidos al anuncio dentro
                    de la ventana de atribución configurada.

                    **Qué valores son buenos.** El umbral operativo es 50 en 7 días: es lo que
                    Meta necesita para salir de la fase de aprendizaje. Por debajo de eso, el
                    ad set queda en «Learning Limited» y todo lo demás se degrada.

                    **Cuándo no fiarse.** Atribuidas no es lo mismo que causadas: Meta se
                    adjudica conversiones que habrían ocurrido de todos modos, y sus números
                    casi nunca cuadran con los de tu backend o tu analítica. Cuenta también
                    conversiones por visualización, no solo por clic. Úsalas para tomar
                    decisiones dentro de la plataforma y contrasta contra tu propia fuente de
                    verdad antes de declarar un resultado de negocio.
                    TEXT,
                'metadata' => [
                    'formula' => 'conteo de eventos de optimización atribuidos',
                    'unit' => 'count',
                    'good_when' => 'higher',
                    'learning_phase_threshold' => 50,
                ],
            ],

            'north_star_metric' => [
                'title' => 'Métrica norte',
                'body' => <<<'TEXT'
                    **Qué es.** La única métrica que decide si una estrategia va bien. No un
                    panel con doce números: uno, elegido a propósito, contra el que se juzga
                    cada experimento y cada propuesta del guardián.

                    **Cómo se elige.** Tiene que cumplir tres cosas: que se mueva con lo que
                    tú haces, que se pueda medir sin discusión, y que si sube, el negocio
                    esté mejor. «Seguidores» falla la tercera casi siempre. «Ingresos» falla
                    la primera cuando el ciclo de venta es largo. Lo habitual que sí funciona:
                    conversiones, ROAS, costo por adquisición o costo por seguidor, según en
                    qué se esté jugando el mes.

                    **Qué valores son buenos.** Ninguno en abstracto. Lo que importa es la
                    dirección contra el punto de partida que quedó registrado al crear la
                    estrategia, y que el resto de las métricas no se hayan deteriorado para
                    conseguirlo.

                    **Cuándo no fiarse.** Cuando cambia a mitad de camino. Cambiar la métrica
                    norte reinicia la comparación: los experimentos anteriores dejan de ser
                    comparables y el histórico deja de significar lo que decía. Si de verdad
                    hace falta cambiarla, lo honesto es abrir otra estrategia.
                    TEXT,
                'metadata' => [
                    'unit' => 'varies',
                    'good_when' => 'depends',
                ],
            ],

            'learning_phase' => [
                'title' => 'Fase de aprendizaje',
                'body' => <<<'TEXT'
                    **Qué es.** El período en el que Meta todavía está averiguando a quién
                    enseñarle un conjunto de anuncios. Mientras dura, la entrega es inestable
                    y el costo por resultado es peor de lo que va a ser.

                    **Cómo se calcula.** Termina cuando el conjunto de anuncios acumula unos
                    **50 eventos de optimización en 7 días**. No 50 clics ni 50 impresiones:
                    50 del evento por el que está optimizando. De ahí sale la fórmula del
                    presupuesto diario mínimo: (CPA objetivo × 50) ÷ 7.

                    **Qué valores son buenos.** Salir de ella. Un conjunto que no llega a los
                    50 eventos se queda en «aprendizaje limitado» y nunca estabiliza: la
                    respuesta no es esperar más, es subir el presupuesto, ampliar el público o
                    optimizar por un evento más frecuente.

                    **Cuándo no fiarse.** De cualquier lectura hecha dentro de ella. Es el
                    error más caro y el más común: pausar por un CPA malo del día 2, o
                    declarar ganador a un anuncio del día 3. Y cada edición significativa
                    —presupuesto, público, creatividad, evento— **reinicia la fase**, así que
                    tocar un conjunto todos los días equivale a no salir nunca de ella.
                    TEXT,
                'metadata' => [
                    'unit' => 'count',
                    'good_when' => 'lower',
                    'learning_phase_threshold' => 50,
                ],
            ],

            'cost_per_follower' => [
                'title' => 'Costo por seguidor',
                'body' => <<<'TEXT'
                    **Qué es.** Lo que cuesta ganar un seguidor nuevo cuando la estrategia
                    tiene el crecimiento de audiencia como métrica norte.

                    **Cómo se calcula.** costo por seguidor = gasto ÷ seguidores netos ganados
                    en el período. Netos: descontando las bajas.

                    **Qué valores son buenos.** Se juzga contra lo que un seguidor termina
                    valiendo. Si de cada 1.000 seguidores se monetizan 10 y cada uno deja 50
                    USD, el valor medio es 0,5 USD por seguidor y ese es el techo del costo
                    aceptable. Sin esa cuenta, la métrica es solo un número.

                    **Cuándo no fiarse.** Meta no ofrece un objetivo nativo de «conseguir
                    seguidores» para Instagram, así que este número casi siempre es una
                    estimación construida a partir del crecimiento observado durante la
                    campaña — le atribuye a la pauta un crecimiento que en parte es orgánico.
                    Además es la métrica más fácil de falsear con contenido viral irrelevante:
                    seguidores baratos que nunca compran salen más caros que ninguno. Míralo
                    junto al engagement rate de las semanas siguientes.
                    TEXT,
                'metadata' => [
                    'formula' => 'gasto / seguidores netos ganados',
                    'unit' => 'currency',
                    'good_when' => 'lower',
                    'estimated' => true,
                ],
            ],
        ];
    }
}
