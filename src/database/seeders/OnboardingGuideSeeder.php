<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Database\Seeder;

/**
 * Console paths taken from spec/2026-08-23-initial-app-development/research/. They change
 * whenever a provider redesigns its console, which is why they live in the database.
 * `images` starts empty: the admin uploads the annotated screenshots later and the
 * frontend renders the text alone until then.
 */
class OnboardingGuideSeeder extends Seeder
{
    private const string LOCALE = 'es';

    public function run(): void
    {
        foreach ($this->entries() as $key => $entry) {
            KnowledgeEntry::query()->firstOrCreate(
                [
                    'type' => KnowledgeType::OnboardingGuide,
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
            'llm-anthropic' => [
                'title' => 'Cómo obtener tu API key de Anthropic (Claude)',
                'body' => <<<'TEXT'
                    1. Entra en **platform.claude.com** e inicia sesión (o crea una cuenta).
                    2. Abre **Settings → API keys**. Enlace directo:
                       https://platform.claude.com/settings/keys
                    3. Pulsa **Create key** y ponle un nombre que te diga para qué es (por
                       ejemplo «Marketing AI Manager»).
                    4. Opcionalmente puedes limitarla a un **workspace** concreto y ponerle
                       una **fecha de expiración**. Si la limitas a un workspace, asegúrate
                       de que sea el que tiene crédito disponible.
                    5. **Copia la key en ese momento**: se muestra una sola vez. Si la
                       pierdes no hay forma de recuperarla, hay que crear otra.
                    6. Pégala en el campo de esta pantalla y guarda.

                    **Cómo se ve una key válida.** Empieza por `sk-ant-api03-`.

                    **Si el botón Create key aparece deshabilitado**, tu usuario no tiene
                    permiso para crear keys en ese workspace: pídeselo a un administrador de
                    la organización.

                    **Qué hace la app al guardar.** Llama a `GET /v1/models` de Anthropic con
                    tu key. Es una llamada gratuita que no consume tokens y solo sirve para
                    confirmar que la key es válida. Si responde 401, la key es incorrecta o
                    fue revocada.
                    TEXT,
                'metadata' => [
                    'images' => [],
                    'docs_url' => 'https://platform.claude.com/settings/keys',
                    'provider' => 'anthropic',
                    'order' => 1,
                ],
            ],

            'llm-openai' => [
                'title' => 'Cómo obtener tu API key de OpenAI',
                'body' => <<<'TEXT'
                    1. Inicia sesión en **platform.openai.com**.
                    2. Ve a la página **API keys**. Enlace directo:
                       https://platform.openai.com/api-keys
                    3. Pulsa **Create new secret key**, ponle un nombre descriptivo y elige
                       el **proyecto** al que quedará asociada.
                    4. **Cópiala inmediatamente**: después de crearla solo verás una versión
                       recortada del valor.
                    5. Pégala en el campo de esta pantalla y guarda.

                    **Cuál de los tres tipos de key necesitas.**
                    - `sk-proj-…` — key de proyecto. Es la normal y la que debes usar.
                    - `sk-svcacct-…` — key de cuenta de servicio. También sirve.
                    - `sk-admin-…` — key de administración. Gestiona proyectos, miembros y
                      facturación, pero **no puede llamar a los modelos**. Si pegas una de
                      estas, la app la rechaza indicándotelo.

                    **Antes de empezar**, comprueba que el proyecto tenga saldo o método de
                    pago configurado: una key válida sin crédito falla en la primera llamada
                    real, no en la verificación.

                    **Qué hace la app al guardar.** Llama a `GET /v1/models` de OpenAI con tu
                    key. Es gratuita y no consume tokens.
                    TEXT,
                'metadata' => [
                    'images' => [],
                    'docs_url' => 'https://platform.openai.com/api-keys',
                    'provider' => 'openai',
                    'order' => 1,
                ],
            ],

            'llm-gemini' => [
                'title' => 'Cómo obtener tu API key de Google Gemini',
                'body' => <<<'TEXT'
                    1. Entra en **Google AI Studio**: https://aistudio.google.com/apikey
                       (también llegas pulsando **Get API key** en la barra lateral de AI
                       Studio).
                    2. Como alternativa: **Dashboard → Projects → API Keys**.
                    3. Pulsa **Create API key**. Si es tu primera vez, AI Studio crea
                       automáticamente un proyecto de Google Cloud por ti.
                    4. Copia la key y pégala en el campo de esta pantalla.

                    **Dos formatos en circulación.** Google está migrando de las keys
                    clásicas `AIza…` a un formato nuevo `AQ.…`. A día de hoy el endpoint REST
                    `generativelanguage.googleapis.com` **rechaza las keys `AQ.`** con un 401
                    `ACCESS_TOKEN_TYPE_UNSUPPORTED`. Si tu cuenta solo genera ese formato y
                    la verificación falla con ese error, no es un fallo de la app: necesitas
                    generar una key `AIza` o contactar con Google.

                    **Restringe la key.** Desde el 19 de junio de 2026 Google bloquea las
                    llamadas a la API de Gemini hechas con keys que no tengan restricciones
                    de API configuradas. En Google Cloud Console, edita la key y restríngela
                    a la **Generative Language API**; una key dejada en «cualquier API» deja
                    de funcionar con Gemini.

                    **Qué hace la app al guardar.** Llama a
                    `GET /v1beta/models?pageSize=1` con la cabecera `x-goog-api-key`. Es
                    gratuita y no consume tokens.
                    TEXT,
                'metadata' => [
                    'images' => [],
                    'docs_url' => 'https://aistudio.google.com/apikey',
                    'provider' => 'gemini',
                    'order' => 1,
                ],
            ],

            'apify' => [
                'title' => 'Cómo obtener tu token de Apify',
                'body' => <<<'TEXT'
                    Apify es el servicio que la app usa para analizar a tu competencia.

                    1. Inicia sesión en **Apify Console**: https://console.apify.com
                    2. Abre **Settings** (en la barra lateral izquierda o en el menú de tu
                       cuenta).
                    3. Entra en la pestaña **API & Integrations**.
                    4. Ahí verás tu **Personal API token**. Pulsa el icono de **Copy**.
                    5. Enlace directo a esa pantalla:
                       https://console.apify.com/settings/integrations
                    6. Pega el token en el campo de esta pantalla y guarda.

                    **Token completo o token limitado.** Por defecto el token da acceso total
                    a tu cuenta. Si activas **«Limit token permissions»** creas un token con
                    alcance restringido. Para esta aplicación basta con un token limitado que
                    pueda **ejecutar cualquier Actor** y **leer datasets** — la app solo corre
                    Actors públicos de la Store y lee sus resultados, nunca crea ni modifica
                    Actors (algo que, de hecho, los tokens limitados no pueden hacer).

                    En esa misma pantalla puedes ponerle fecha de expiración al token y
                    rotarlo si crees que se ha filtrado.

                    **Qué hace la app al guardar.** Llama a `GET /v2/users/me` de Apify. Si
                    responde 200, el token es válido y la app guarda el identificador de tu
                    cuenta. Si responde 401, el token es incorrecto o fue revocado.
                    TEXT,
                'metadata' => [
                    'images' => [],
                    'docs_url' => 'https://console.apify.com/settings/integrations',
                    'provider' => 'apify',
                    'order' => 2,
                ],
            ],

            'meta' => [
                'title' => 'Cómo conectar Meta (Instagram y Facebook Ads)',
                'body' => <<<'TEXT'
                    Meta no se conecta con una key manual: se conecta con un botón de OAuth.
                    Pero para que ese botón exista tiene que haber una **app de Meta** creada
                    por quien administra esta instalación. Esta guía cubre las dos partes.

                    ## Parte 1 — Crear la app de Meta (una sola vez, quien administra)

                    1. Entra en https://developers.facebook.com/ e inicia sesión.
                    2. Abre el desplegable **My Apps** y pulsa **Create App**.
                    3. Una vez creada, ve a **+ Add Product** y añade **Marketing API**.
                       Añade también **Facebook Login**, que es lo que hace funcionar el
                       botón de conexión.
                    4. En **App settings → Basic** copia el **App ID** y el **App Secret**:
                       son las credenciales de plataforma y van en la configuración del
                       servidor, no en el panel de usuario.
                    5. En **Facebook Login → Settings**, añade la URL de callback de esta
                       instalación a **Valid OAuth Redirect URIs**. Debe coincidir
                       exactamente, carácter por carácter.

                    ## Parte 2 — Permisos y modo de desarrollo

                    Los permisos que la app solicita son:
                    `ads_management`, `ads_read`, `business_management`, `pages_show_list`,
                    `pages_read_engagement`, `instagram_basic`, `instagram_manage_insights`.

                    Una app en **Development mode** solo puede pedir permisos a **usuarios con
                    rol en la app** — administradores, desarrolladores y testers. Para esos
                    usuarios **no hace falta pasar App Review** y se pueden manejar cuentas
                    publicitarias reales. Añade a cada persona que vaya a usar la app en
                    **App Roles → Roles** antes de que intente conectarse.

                    App Review solo es necesario para servir a personas que **no** tienen rol
                    en la app. Ten en cuenta también que los datos generados en Development
                    mode se vuelven visibles para todos los usuarios en cuanto pases la app a
                    modo Live.

                    Aparte de eso, el **nivel de acceso a la Marketing API** limita el
                    volumen, no las capacidades: por defecto estás en **Limited /
                    Development Access**, con un límite de aproximadamente 300 + 40 llamadas
                    por hora por cada anuncio activo. Para subir a acceso completo hacen falta
                    al menos 500 llamadas en los últimos 15 días con menos de un 15 % de
                    errores, y después pasar App Review.

                    ## Parte 3 — Cuenta publicitaria sandbox (recomendada para probar)

                    Una cuenta sandbox acepta todas las llamadas de la Marketing API pero
                    **nunca entrega anuncios ni gasta dinero**. Para crearla:

                    1. Entra en https://developers.facebook.com/
                    2. Abre el desplegable **My Apps**.
                    3. Elige una app en la que tengas acceso de **Administrator** o
                       **Developer**.
                    4. Selecciona **Marketing API** (o añádelo con **+ Add Product**).
                    5. Dentro de **Marketing API**, entra en **Tools**.
                    6. Desde ahí accedes al **Sandbox Mode** y creas la cuenta.

                    Límites de la sandbox: **solo puedes tener una** por app, no necesita
                    método de pago, no acumula impresiones ni gasto, y **no se ve en Ads
                    Manager ni en Power Editor**. Como los anuncios no se entregan, tampoco
                    genera métricas reales: sirve para validar el flujo de creación, no para
                    evaluar rendimiento. En esta app se activa con el checkbox **«Modo
                    sandbox»** en Configuración.

                    ## Parte 4 — Conectar tu cuenta (cada usuario)

                    Pulsa **Conectar con Meta** en esta pantalla y acepta los permisos en la
                    ventana de Facebook. La app guarda un token de larga duración.

                    **Importante:** los tokens de Meta duran unos **60 días y no se pueden
                    renovar automáticamente** — Meta no ofrece refresh token para este flujo.
                    Cuando falten menos de 7 días para que caduque, la app te pedirá volver a
                    conectarte. Es normal y no significa que algo esté roto.

                    **Qué hace la app al conectar.** Intercambia el token corto por uno largo
                    y llama a `GET /me/adaccounts` para listar tus cuentas publicitarias. Si
                    la lista viene vacía, el permiso se concedió pero sin acceso a ninguna
                    cuenta: revisa en Business Manager que tu usuario tenga acceso a la cuenta
                    publicitaria que quieres usar.
                    TEXT,
                'metadata' => [
                    'images' => [],
                    'docs_url' => 'https://developers.facebook.com/docs/marketing-apis/',
                    'provider' => 'meta',
                    'order' => 3,
                ],
            ],

            'google' => [
                'title' => 'Cómo conectar Google (Drive y YouTube)',
                'body' => <<<'TEXT'
                    Google se conecta con un botón de OAuth. Igual que con Meta, quien
                    administra la instalación tiene que preparar antes un proyecto en Google
                    Cloud.

                    ## ⚠️ Lo más importante de toda esta guía

                    **La pantalla de consentimiento tiene que quedar publicada en
                    Production.** Un proyecto con la pantalla de consentimiento de tipo
                    externo y estado de publicación **«Testing» emite refresh tokens que
                    caducan a los 7 días**. Es literal y está documentado por Google.

                    Consecuencia práctica: si dejas el proyecto en Testing, la conexión con
                    Google se romperá cada semana, los usuarios tendrán que volver a
                    autorizar una y otra vez, y los jobs en segundo plano fallarán con
                    `invalid_grant` sin explicación aparente. Es el error más caro que puede
                    cometer quien administra esta aplicación. Publicar a Production **no
                    exige verificación** para los scopes que esta app usa.

                    ## Parte 1 — Proyecto y APIs

                    1. Entra en **Google Cloud Console** (https://console.cloud.google.com) y
                       crea un proyecto o selecciona uno existente.
                    2. Ve a **APIs & Services → Library** y habilita:
                       - **Google Drive API** — para la biblioteca de piezas audiovisuales.
                       - **YouTube Data API v3** — opcional, solo si vas a analizar YouTube.
                    3. Ten presente la cuota de YouTube: **100 llamadas a `search.list` al
                       día**, 100 a `videos.insert` y 10.000 unidades diarias para todo lo
                       demás. Son buckets separados y no se pueden intercambiar.

                    ## Parte 2 — Pantalla de consentimiento

                    4. Ve a **Google Auth Platform → Audience** (antes llamada «OAuth consent
                       screen»).
                    5. Configura el tipo de usuario **External** y completa los datos de la
                       aplicación.
                    6. Añade exactamente estos scopes:
                       - `openid`
                       - `email`
                       - `profile`
                       - `https://www.googleapis.com/auth/drive.file`
                       - `https://www.googleapis.com/auth/youtube.readonly` (solo si usarás
                         YouTube)
                    7. **Pulsa «Publish app»** y confirma que el **Publishing status** pasa a
                       **In production**. No dejes el proyecto en Testing: ver el aviso del
                       principio.

                    Sobre `drive.file`: es un scope **no sensible** que solo da acceso a los
                    archivos que la propia app crea o que el usuario le comparte
                    explícitamente. La app **no puede** listar ni leer el resto de tu Drive.
                    Es deliberado.

                    ## Parte 3 — Cliente OAuth

                    8. Ve a **APIs & Services → Credentials → Create credentials → OAuth
                       client ID**, tipo **Web application**.
                    9. Añade la URL de callback de esta instalación en **Authorized redirect
                       URIs**; debe coincidir exactamente con la configurada en el servidor.
                    10. Copia el **Client ID** y el **Client Secret** a la configuración del
                        servidor. Son credenciales de plataforma, no de usuario.

                    ## Parte 4 — Conectar tu cuenta (cada usuario)

                    Pulsa **Conectar con Google** en esta pantalla y acepta los permisos.

                    **Qué hace la app al conectar.** Pide autorización con
                    `access_type=offline` y `prompt=consent` para recibir un **refresh
                    token**, y lo guarda cifrado. A partir de ahí renueva el acceso sola, sin
                    volver a molestarte.

                    **Otras causas por las que un refresh token deja de valer**, además del
                    estado Testing: que revoques el acceso a la app, que no se use durante
                    seis meses, o que superes el límite de 100 refresh tokens por cuenta y
                    cliente OAuth (el número 101 invalida silenciosamente al más antiguo). Si
                    ves el error `invalid_grant`, la conexión está muerta y hay que volver a
                    autorizar: no se recupera reintentando.
                    TEXT,
                'metadata' => [
                    'images' => [],
                    'docs_url' => 'https://developers.google.com/identity/protocols/oauth2',
                    'provider' => 'google',
                    'order' => 4,
                ],
            ],
        ];
    }
}
