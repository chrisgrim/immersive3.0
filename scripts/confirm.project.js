import { createInterface } from 'readline';
import { dirname, basename } from 'path';
import { fileURLToPath } from 'url';
import { readFileSync, existsSync } from 'fs';

// Get project directory name
const __dirname = dirname(fileURLToPath(import.meta.url));
const projectPath = dirname(__dirname);
const projectName = basename(projectPath);

// Determine which build environment was called
const isProductionBuild = process.argv.includes('--production') || 
                          process.env.NODE_ENV === 'production';
const isStagingBuild = process.argv.includes('--staging');

// Parse .env file to get APP_ENV
function parseEnvFile() {
  try {
    const envPath = `${projectPath}/.env`;
    if (!existsSync(envPath)) {
      return { error: 'No .env file found' };
    }
    
    const envContent = readFileSync(envPath, 'utf8');
    const envVars = {};
    
    envContent.split('\n').forEach(line => {
      // Skip comments and empty lines
      if (!line || line.startsWith('#')) return;
      
      const matches = line.match(/^([^=]+)=(.*)$/);
      if (matches) {
        const key = matches[1].trim();
        const value = matches[2].trim().replace(/^["'](.*)["']$/, '$1'); // Remove quotes if present
        envVars[key] = value;
      }
    });
    
    return { envVars };
  } catch (error) {
    return { error: `Error reading .env file: ${error.message}` };
  }
}

// Start the confirmation process
try {
  const packageJson = JSON.parse(readFileSync(`${projectPath}/package.json`, 'utf8'));
  const packageName = packageJson.name;
  
  // Read .env file
  const { envVars, error } = parseEnvFile();
  
  // Create readline interface
  const rl = createInterface({
    input: process.stdin,
    output: process.stdout
  });

  console.log('\x1b[33m%s\x1b[0m', '⚠️  PRODUCTION BUILD CONFIRMATION ⚠️');
  console.log('\x1b[36m%s\x1b[0m', `You are about to build the "${packageName}" project`);
  console.log('\x1b[36m%s\x1b[0m', `Current directory: ${projectPath}`);
  
  // Display environment information
  if (error) {
    console.log('\x1b[31m%s\x1b[0m', `Warning: ${error}`);
  } else {
    const appEnv = envVars.APP_ENV || 'unknown';
    console.log('\x1b[36m%s\x1b[0m', `Environment (.env APP_ENV): ${appEnv}`);
    
    // Verify environment matches build type
    if (isProductionBuild && appEnv !== 'production' && appEnv !== 'staging') {
      console.log('\x1b[31m%s\x1b[0m', `⚠️ WARNING: You're running a production build but your APP_ENV is set to "${appEnv}"`);
      console.log('\x1b[31m%s\x1b[0m', `This might indicate you're building for the wrong environment!`);
    } else if (!isProductionBuild && !process.argv.includes('--staging') && (appEnv === 'production' || appEnv === 'staging')) {
      console.log('\x1b[31m%s\x1b[0m', `⚠️ WARNING: Your APP_ENV is set to "${appEnv}" but you're not running a production build`);
    } else if (process.argv.includes('--staging') && appEnv !== 'staging') {
      console.log('\x1b[31m%s\x1b[0m', `⚠️ WARNING: You're running a staging build but your APP_ENV is set to "${appEnv}"`);
    } else {
      console.log('\x1b[32m%s\x1b[0m', `✓ Build type and environment appear to match`);
    }
  }
  
  rl.question('\x1b[1m\x1b[33mAre you sure you want to proceed? (y/n): \x1b[0m', (answer) => {
    if (answer.toLowerCase() !== 'y' && answer.toLowerCase() !== 'yes') {
      console.log('\x1b[31m%s\x1b[0m', 'Build cancelled');
      process.exit(1);
    }
    
    console.log('\x1b[32m%s\x1b[0m', 'Proceeding with build...');
    rl.close();
  });
} catch (error) {
  console.error('\x1b[31m%s\x1b[0m', 'Error reading package.json:');
  console.error(error);
  process.exit(1);
} 