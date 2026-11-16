// Máy chủ giả lập endpoint upload của TikTok cho test song song: node tests/js/slow-server.cjs <port>
// Mỗi request mất FAST_MS (hoặc SLOW_MS cho SLOW_FIRST request đầu), đồng thời đếm số request chạy cùng lúc.
const http = require('http');

const port = Number(process.argv[2]);
const slowFirst = Number(process.env.SLOW_FIRST || 0);
const slowMs = Number(process.env.SLOW_MS || 1500);
const fastMs = Number(process.env.FAST_MS || 300);

let active = 0;
let max = 0;
let count = 0;

http.createServer((req, res) => {
    if (req.url === '/stats') {
        res.setHeader('Content-Type', 'application/json');
        res.end(JSON.stringify({ max, count }));
        return;
    }

    const n = ++count;
    active++;
    max = Math.max(max, active);

    req.resume();
    req.on('end', () => {
        setTimeout(() => {
            active--;
            res.setHeader('Content-Type', 'application/json');
            res.end(JSON.stringify({ code: 0, data: { src: `https://cdn.test/img${n}~o.png` } }));
        }, n <= slowFirst ? slowMs : fastMs);
    });
}).listen(port, '127.0.0.1', () => console.log('READY'));
