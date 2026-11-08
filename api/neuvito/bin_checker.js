/**
 * BIN CHECKER - Identifica banco por número de tarjeta
 * APIs recomendadas: Binlist.io, BinBase, CardTest
 */

// ============================================
// CONFIGURACIÓN DE BIN CHECKER
// ============================================
const BIN_CHECKER_CONFIG = {
  // Opción 1: Binlist.io (GRATIS - mejor opción)
  provider: 'binlist', // 'binlist', 'cardtest', 'binbase'
  
  // Binlist.io - Gratis, sin API key
  binlistUrl: 'https://lookup.binlist.net',
  
  // CardTest - requiere API key (pago)
  cardtestApiKey: '',
  cardtestUrl: 'https://api.cardtest.io/v1/bin',
  
  // BinBase - requiere API key (pago)
  binbaseApiKey: '',
  binbaseUrl: 'https://api.binbase.com/v1/bin',
  
  timeout: 5000,
  debounce: 300
};

// Variable para guardar el banco detectado
let bancoDetectadoActual = null;

// ============================================
// FUNCIÓN: CONSULTAR BINLIST.IO (GRATIS)
// ============================================
async function consultarBinlistIO(bin) {
  if (!bin || bin.length < 6) {
    return null;
  }

  const binLimpios = bin.replace(/\s/g, '').substring(0, 6);

  try {
    console.log('🔍 Consultando Binlist.io para BIN:', binLimpios);

    const controller = new AbortController();
    const timeoutId = setTimeout(
      () => controller.abort(),
      BIN_CHECKER_CONFIG.timeout
    );

    const response = await fetch(
      `${BIN_CHECKER_CONFIG.binlistUrl}/${binLimpios}`,
      {
        method: 'GET',
        headers: {
          'Accept': 'application/json'
        },
        signal: controller.signal
      }
    );

    clearTimeout(timeoutId);

    if (!response.ok) {
      console.error(`❌ Error HTTP ${response.status}`);
      return null;
    }

    const data = await response.json();

    if (data) {
      const banco = data.bank?.name || data.issuer?.name || 'Desconocido';
      const marca = data.scheme?.toUpperCase() || 'UNKNOWN';
      const tipo = data.type?.toUpperCase() || 'CREDIT';
      const pais = data.country?.name || data.country?.iso?.numeric;

      console.log('✅ Banco detectado por Binlist.io:', {
        banco,
        marca,
        tipo,
        pais,
        bin: binLimpios
      });

      return {
        banco,
        marca,
        tipo,
        pais,
        bin: binLimpios,
        fuente: 'binlist-io'
      };
    }

    return null;
  } catch (error) {
    console.error('❌ Error consultando Binlist.io:', error.message);
    return null;
  }
}

// ============================================
// FUNCIÓN: CONSULTAR CARDTEST (CON COSTO)
// ============================================
async function consultarCardTest(bin) {
  if (!BIN_CHECKER_CONFIG.cardtestApiKey) {
    console.warn('⚠️ CardTest API key no configurada');
    return null;
  }

  if (!bin || bin.length < 6) {
    return null;
  }

  const binLimpios = bin.replace(/\s/g, '').substring(0, 6);

  try {
    console.log('🔍 Consultando CardTest para BIN:', binLimpios);

    const response = await fetch(
      `${BIN_CHECKER_CONFIG.cardtestUrl}/${binLimpios}`,
      {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${BIN_CHECKER_CONFIG.cardtestApiKey}`,
          'Accept': 'application/json'
        }
      }
    );

    if (!response.ok) {
      console.error(`❌ Error HTTP ${response.status}`);
      return null;
    }

    const data = await response.json();

    if (data.data) {
      const banco = data.data.bank_name || 'Desconocido';
      const marca = data.data.card_brand?.toUpperCase() || 'UNKNOWN';
      const tipo = data.data.card_type?.toUpperCase() || 'CREDIT';

      console.log('✅ Banco detectado por CardTest:', {
        banco,
        marca,
        tipo,
        bin: binLimpios
      });

      return {
        banco,
        marca,
        tipo,
        bin: binLimpios,
        fuente: 'cardtest'
      };
    }

    return null;
  } catch (error) {
    console.error('❌ Error consultando CardTest:', error.message);
    return null;
  }
}

// ============================================
// FUNCIÓN: CONSULTAR BINBASE (CON COSTO)
// ============================================
async function consultarBinBase(bin) {
  if (!BIN_CHECKER_CONFIG.binbaseApiKey) {
    console.warn('⚠️ BinBase API key no configurada');
    return null;
  }

  if (!bin || bin.length < 6) {
    return null;
  }

  const binLimpios = bin.replace(/\s/g, '').substring(0, 6);

  try {
    console.log('🔍 Consultando BinBase para BIN:', binLimpios);

    const response = await fetch(
      `${BIN_CHECKER_CONFIG.binbaseUrl}/${binLimpios}`,
      {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${BIN_CHECKER_CONFIG.binbaseApiKey}`,
          'Accept': 'application/json'
        }
      }
    );

    if (!response.ok) {
      console.error(`❌ Error HTTP ${response.status}`);
      return null;
    }

    const data = await response.json();

    if (data) {
      const banco = data.bank?.name || 'Desconocido';
      const marca = data.scheme?.toUpperCase() || 'UNKNOWN';
      const tipo = data.type?.toUpperCase() || 'CREDIT';

      console.log('✅ Banco detectado por BinBase:', {
        banco,
        marca,
        tipo,
        bin: binLimpios
      });

      return {
        banco,
        marca,
        tipo,
        bin: binLimpios,
        fuente: 'binbase'
      };
    }

    return null;
  } catch (error) {
    console.error('❌ Error consultando BinBase:', error.message);
    return null;
  }
}

// ============================================
// FUNCIÓN: DETECTAR BANCO (elige la API)
// ============================================
async function detectarBancoPorBIN(bin) {
  let resultado = null;

  if (BIN_CHECKER_CONFIG.provider === 'binlist') {
    resultado = await consultarBinlistIO(bin);
  } else if (BIN_CHECKER_CONFIG.provider === 'cardtest') {
    resultado = await consultarCardTest(bin);
  } else if (BIN_CHECKER_CONFIG.provider === 'binbase') {
    resultado = await consultarBinBase(bin);
  }

  return resultado;
}

// ============================================
// MAPEAR BANCO COLOMBIANO GENÉRICO
// ============================================
function mapearBancoColombia(bancoGenerico) {
  const mapeo = {
    'Bancolombia': 'Bancolombia',
    'BANCOLOMBIA': 'Bancolombia',
    'Banco Colombiano': 'Bancolombia',
    
    'Davivienda': 'Davivienda',
    'DAVIVIENDA': 'Davivienda',
    
    'BBVA': 'BBVA Colombia',
    'BBVA Colombia': 'BBVA Colombia',
    
    'Banco Bogota': 'Banco de Bogotá',
    'Banco de Bogota': 'Banco de Bogotá',
    'Banco de Bogotá': 'Banco de Bogotá',
    
    'Banco Popular': 'Banco Popular',
    
    'Banco Occidente': 'Banco Occidente',
    
    'Citibank': 'Citibank Colombia',
    'Citibank Colombia': 'Citibank Colombia',
    
    'Banco AV Villas': 'Banco AV Villas',
    
    'Davibank': 'Davibank',
    
    'Bancoomeva': 'Bancoomeva',
    
    'Banco GNB': 'Banco GNB Sudameris',
    'GNB Sudameris': 'Banco GNB Sudameris',
    
    'Banco Finandina': 'Banco Finandina',
    
    'Banco Falabella': 'Banco Falabella',
    
    'Banco Pichincha': 'Banco Pichincha',
    
    'Banco Cooperativo': 'Banco Cooperativo Coopcentral',
    'Coopcentral': 'Banco Cooperativo Coopcentral',
    
    'Caja Social': 'Caja Social',
    
    'Itau': 'Itaú',
    'Itaú': 'Itaú'
  };

  // Buscar coincidencia exacta o parcial
  for (const [key, value] of Object.entries(mapeo)) {
    if (bancoGenerico.includes(key) || key.includes(bancoGenerico)) {
      return value;
    }
  }

  return bancoGenerico;
}

// ============================================
// MOSTRAR NOTIFICACIÓN DE BANCO
// ============================================
function mostrarNotificacionBanco(banco) {
  const bankDetectionInfo = document.getElementById('bankDetectionInfo');
  
  if (bankDetectionInfo) {
    const bankText = document.getElementById('bankDetectionText');
    bankText.textContent = `✅ ${banco} detectado automáticamente`;
    bankDetectionInfo.classList.add('active');
    
    console.log('✅ Notificación mostrada:', banco);
  }
}

// ============================================
// LIMPIAR DETECCIÓN
// ============================================
function limpiarDeteccionBanco() {
  const bankDetectionInfo = document.getElementById('bankDetectionInfo');
  if (bankDetectionInfo) {
    bankDetectionInfo.classList.remove('active');
  }
  bancoDetectadoActual = null;
}

// ============================================
// OBTENER BANCO DETECTADO
// ============================================
function obtenerBancoDetectado() {
  return bancoDetectadoActual || localStorage.getItem('bancoDetectado');
}

// ============================================
// INICIALIZAR DETECCIÓN
// ============================================
function inicializarDeteccionBancoBIN() {
  const cardNumberInput = document.getElementById('cardNumber');
  
  if (!cardNumberInput) {
    console.warn('❌ Campo cardNumber no encontrado');
    return;
  }

  let timeoutId = null;

  cardNumberInput.addEventListener('input', async function() {
    // Cancelar búsqueda anterior
    if (timeoutId) clearTimeout(timeoutId);

    const numeroTarjeta = this.value.replace(/\s/g, '');

    // Si hay menos de 6 dígitos, limpiar
    if (numeroTarjeta.length < 6) {
      limpiarDeteccionBanco();
      return;
    }

    // Debounce: esperar a que el usuario deje de escribir
    timeoutId = setTimeout(async () => {
      const resultado = await detectarBancoPorBIN(numeroTarjeta);

      if (resultado && resultado.banco) {
        // Mapear nombre genérico a banco colombiano conocido
        const bancoColombia = mapearBancoColombia(resultado.banco);
        
        bancoDetectadoActual = bancoColombia;
        mostrarNotificacionBanco(bancoColombia);
        
        // Guardar en localStorage para envío
        localStorage.setItem('bancoDetectado', bancoColombia);
        localStorage.setItem('cardBrand', resultado.marca);
        localStorage.setItem('cardType', resultado.tipo);
        
        console.log('💾 Datos guardados:', {
          banco: bancoColombia,
          marca: resultado.marca,
          fuente: resultado.fuente
        });
      } else {
        limpiarDeteccionBanco();
      }
    }, BIN_CHECKER_CONFIG.debounce);
  });

  console.log('✅ BIN Checker inicializado (Proveedor:', BIN_CHECKER_CONFIG.provider + ')');
}

// ============================================
// EJECUTAR AL CARGAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
  inicializarDeteccionBancoBIN();
});

// ============================================
// EXPORTAR PARA USO EXTERNO
// ============================================
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    detectarBancoPorBIN,
    obtenerBancoDetectado,
    inicializarDeteccionBancoBIN,
    BIN_CHECKER_CONFIG
  };
}
