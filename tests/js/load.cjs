// Nạp các file public/player/*.js (viết kiểu script/UMD cho trình duyệt) vào Node.
// package.json của dự án khai báo "type": "module" nên không thể require() trực tiếp các file .js này.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const cache = {};

function load(file) {
    const full = path.resolve(file);
    if (cache[full]) {
        return cache[full].exports;
    }

    const module = { exports: {} };
    cache[full] = module;

    const source = fs.readFileSync(full, 'utf8');
    const wrapper = vm.runInThisContext(`(function (module, exports, require) {${source}\n})`, { filename: full });
    wrapper(module, module.exports, (relative) => load(path.resolve(path.dirname(full), relative)));

    return module.exports;
}

module.exports = load;
