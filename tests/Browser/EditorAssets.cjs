const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../../www');

// 운영 서버 요청 없이 배포본의 CKEditor와 본문 스타일을 그대로 사용한다.
module.exports = request => {
  const match = new URL(request.url()).pathname.match(/\/(vendor\/ckeditor4\/.+|assets\/editor-content\.css)$/);
  if (!match) return false;
  const file = path.resolve(root, match[1]);
  if (!file.startsWith(root + path.sep) || !fs.existsSync(file) || !fs.statSync(file).isFile()) {
    request.respond({status: 404, body: ''});
    return true;
  }
  const types = {'.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.gif': 'image/gif', '.svg': 'image/svg+xml'};
  request.respond({status: 200, contentType: types[path.extname(file)] || 'application/octet-stream', body: fs.readFileSync(file)});
  return true;
};
