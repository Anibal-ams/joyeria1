const fs = require('fs');
const path = require('path');

// Añade aquí las carpetas que quieres ignorar
const carpetasIgnoradas = ['node_modules', 'vendor', '.git', 'script', 'vendor'];

function generarEstructura(dir, indentacion = '', esUltimo = true) {
  let estructura = '';
  const archivos = fs.readdirSync(dir);
  
  archivos.forEach((archivo, indice) => {
    if (carpetasIgnoradas.includes(archivo)) {
      return; // Ignora esta carpeta
    }

    const esUltimoItem = indice === archivos.length - 1;
    const rutaArchivo = path.join(dir, archivo);
    const stats = fs.statSync(rutaArchivo);
    
    if (stats.isDirectory()) {
      estructura += `${indentacion}${esUltimo ? '└── ' : '├── '}📁 ${archivo}\n`;
      estructura += generarEstructura(rutaArchivo, indentacion + (esUltimo ? '    ' : '│   '), esUltimoItem);
    } else {
      estructura += `${indentacion}${esUltimo ? '└── ' : '├── '}📄 ${archivo}\n`;
    }
  });
  
  return estructura;
}

const raizProyecto = process.cwd();
const archivoSalida = 'estructura_proyecto.txt';

console.log('Generando estructura del proyecto...');

const estructura = generarEstructura(raizProyecto);

fs.writeFileSync(archivoSalida, estructura);

console.log(`La estructura del proyecto ha sido escrita en ${archivoSalida}`);
console.log('Aquí tienes una vista previa de la estructura:');
console.log(estructura);