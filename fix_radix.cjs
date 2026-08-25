const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, 'resources/js/Components/ui');
const files = fs.readdirSync(dir);

let fixedCount = 0;

files.forEach(file => {
    if(!file.endsWith('.tsx')) return;
    const p = path.join(dir, file);
    let content = fs.readFileSync(p, 'utf8');
    
    const match = content.match(/import\s+\*\s+as\s+(\w+)Primitive\s+from\s+['"]@radix-ui\/([^'"]+)['"]/);
    if(match) {
        const namespace = match[1];
        const regex2 = new RegExp(namespace + 'Primitive\\.(\\w+)', 'g');
        let used = [];
        let m;
        while((m = regex2.exec(content)) !== null) {
            if(!used.includes(m[1])) used.push(m[1]);
        }
        
        if(used.length > 0) {
            const newImport = `import { ${used.join(', ')} } from "@radix-ui/${match[2]}"`;
            content = content.replace(match[0], newImport);
            
            used.forEach(item => {
                const repReg = new RegExp(namespace + 'Primitive\\.' + item, 'g');
                content = content.replace(repReg, item);
            });
            
            fs.writeFileSync(p, content, 'utf8');
            console.log(`[FIXED] ${file}`);
            fixedCount++;
        }
    }
});

console.log(`Total fixed: ${fixedCount}`);
