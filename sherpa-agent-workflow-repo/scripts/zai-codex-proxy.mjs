import http from "node:http";
import crypto from "node:crypto";
import fs from "node:fs";
import { exec } from "node:child_process";

function log(msg) {
  const time = new Date().toISOString();
  fs.appendFileSync("/tmp/proxy_debug.log", `[${time}] ${msg}\n`);
}

function notifyUser() {
  // Z.ai 전용 알림 (IDE 알림과 별도로 Thinking 기능 사용 시 완료 신호)
  exec('afplay /System/Library/Sounds/Glass.aiff');
}

const host = process.env.ZAI_CODEX_PROXY_HOST || "127.0.0.1";
const port = Number(process.env.ZAI_CODEX_PROXY_PORT || "4000");
const zaiBaseUrl = process.env.ZAI_API_BASE || "https://api.z.ai/api/coding/paas/v4";
const zaiApiKey = process.env.Z_AI_API_KEY;
const proxyKey = process.env.ZAI_CODEX_PROXY_KEY;

const server = http.createServer(async (req, res) => {
  try {
    const url = new URL(req.url || "/", `http://${host}:${port}`);
    log(`[Proxy] ${req.method} ${url.pathname}`);

    if (!isAuthorized(req)) {
      return sendJson(res, 401, { error: "Unauthorized" });
    }

    if (req.method === "GET" && url.pathname === "/v1/models") {
      return proxyModels(res);
    }

    if (req.method === "POST" && url.pathname === "/v1/responses") {
      const body = await readJson(req);
      return proxyResponse(body, res);
    }

    return sendJson(res, 404, { error: "Not found" });
  } catch (error) {
    log(`[Proxy] Error: ${error.message}`);
    return sendJson(res, 500, { error: error.message });
  }
});

server.listen(port, host, () => {
  console.error(`Z.ai Specialized Proxy listening on http://${host}:${port}`);
});

function isAuthorized(req) {
  const auth = (req.headers.authorization || "").trim();
  return auth === `Bearer ${proxyKey}` || auth === proxyKey;
}

async function proxyModels(res) {
  const upstream = await fetch(`${zaiBaseUrl}/models`, {
    headers: { Authorization: `Bearer ${zaiApiKey}`, "User-Agent": "Cursor/0.42.3" },
  });
  const data = await upstream.text();
  res.writeHead(upstream.status, { "content-type": "application/json" });
  res.end(data);
}

async function proxyResponse(body, res) {
  const chatBody = {
    model: "GLM-5.1",
    messages: transformMessages(body),
    stream: Boolean(body.stream),
    thinking: { type: "enabled" },
  };

  const response = await fetch(`${zaiBaseUrl}/chat/completions`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${zaiApiKey}`,
      "Content-Type": "application/json",
      "User-Agent": "Cursor/0.42.3"
    },
    body: JSON.stringify(chatBody),
  });

  if (!response.ok) {
    const errorText = await response.text();
    return sendJson(res, response.status, { error: errorText });
  }

  if (body.stream) {
    return handleStream(response, body, res);
  } else {
    const data = await response.json();
    sendJson(res, 200, chatToResponse(data, body));
    notifyUser();
  }
}

function transformMessages(body) {
  const messages = [];
  if (body.instructions) messages.push({ role: "system", content: body.instructions });
  
  const inputs = Array.isArray(body.input) ? body.input : [];
  for (const item of inputs) {
    if (item.type === "message" || item.role) {
      let content = item.content;
      
      // 만약 content가 배열이거나 객체라면 문자열로 추출 (1214 에러 방지)
      if (Array.isArray(content)) {
        content = content.map(part => typeof part === 'object' ? (part.text || "") : part).join("");
      } else if (typeof content === 'object' && content !== null) {
        content = content.text || JSON.stringify(content);
      }
      
      messages.push({ role: item.role || "user", content: content });
    }
  }
  messages.unshift({ role: "system", content: "Please always respond in Korean." });
  return messages;
}

async function handleStream(response, requestBody, res) {
  res.writeHead(200, { "Content-Type": "text/event-stream" });
  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    const chunk = decoder.decode(value, { stream: true });
    // 여기서 reasoning_content를 일반 content로 변환하여 Codex가 이해하게 함
    const processed = processZaiChunk(chunk);
    res.write(processed);
  }
  res.end();
  notifyUser();
}

function processZaiChunk(chunk) {
  // 단순 변환 로직 (필요 시 정교화 가능)
  return chunk.replace(/"reasoning_content":"/g, '"content":"> [Thinking]\\n> ');
}

function chatToResponse(data, body) {
  const content = data.choices?.[0]?.message?.content || "";
  const reasoning = data.choices?.[0]?.message?.reasoning_content || "";
  const combined = reasoning ? `> [Thinking]\n> ${reasoning.replace(/\n/g, "\n> ")}\n\n${content}` : content;
  
  return {
    id: data.id,
    object: "response",
    status: "completed",
    model: "GLM-5.1",
    output: [{
      type: "message",
      role: "assistant",
      content: [{ type: "output_text", text: combined }]
    }]
  };
}

function sendJson(res, status, data) {
  res.writeHead(status, { "content-type": "application/json" });
  res.end(JSON.stringify(data));
}

async function readJson(req) {
  const buffers = [];
  for await (const chunk of req) buffers.push(chunk);
  const data = Buffer.concat(buffers).toString();
  return data ? JSON.parse(data) : {};
}
