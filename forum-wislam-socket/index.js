const axios = require('axios');
const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const { createAdapter } = require('@socket.io/redis-adapter');
const Redis = require('ioredis');
const hostOriginPort = 'http://192.168.1.6:8000';
const hostOrigin = '192.168.1.6';


const redis = new Redis({
  host: '127.0.0.1', port: 6379,
  // password: 'your_secure_password' 
});
redis.on('connect', () => console.log('Connected to Valkey'));
redis.on('error', (err) => console.error('Valkey error:', err));
// await redisStream.connect();

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: hostOriginPort, // origin của frontend
    methods: ["GET", "POST"],
    credentials: true // nếu frontend dùng cookies/session
  }
});
const rateLimit = require('express-rate-limit');
const limiter = rateLimit({ windowMs: 15 * 60 * 1000, max: 100 });
app.use(limiter);

io.use(async (socket, next) => {
  const sessionCookie = socket.handshake.headers.cookie;

  try {
    const response = await axios.get(hostOriginPort + '/user', {
      headers: {
        Cookie: sessionCookie,
      },
      withCredentials: true
    });

    socket.user = response.data;
    console.log(socket.user.name);
    next();
  } catch (error) {
    console.error('Auth error:', error.response?.status);
    next(new Error('Unauthorized'));
  }
});

const pubClient = new Redis({
  host: '127.0.0.1',
  port: 6379,
});
const subClient = pubClient.duplicate();

io.adapter(createAdapter(pubClient, subClient));

const redisStream = new Redis({
  host: '127.0.0.1',
  port: 6379,
});

const redisData = new Redis({
  host: '127.0.0.1',
  port: 6379,
});


io.on('connection', (socket) => {
  let conversationId = null;
  console.log('A user connected:', socket.id);

  socket.on('join_conversation', async (convId) => {
    console.log(`[JOIN] socket: ${socket.id}, convId: ${convId}`);
    conversationId = convId;
    try {
      const sessionCookie = socket.handshake.headers.cookie;

      const response = await axios.get(`${hostOriginPort}/conversation-user/authority/${convId}/access`, {
        headers: { Cookie: sessionCookie },
        withCredentials: true,
      });

      if (response.data.authorized) {
        await redisData.sadd(`conversation:${convId}:online`, socket.user.user_id);
        const onlineUsers = await redisData.smembers(`conversation:${conversationId}:online`);

        socket.join(convId);

        socket.emit('user_status', {
          user_id: socket.user.user_id,
          online: true,
          online_users: onlineUsers
        });

        socket.to(convId).emit('user_status', {
          user_id: socket.user.user_id,
          online: true,
          online_users: onlineUsers
        });

        console.log(`User ${socket.id} joined conversation ${convId}`);
      } else {
        socket.emit('error', 'Access denied');
        console.log(`[ACCESS] denied for conv ${convId}`);
      }

    } catch (err) {
      console.error('[JOIN ERROR]', err);
      socket.emit('error', 'Access check failed');
    }
  });

  socket.on('check_online', async ({ conversation_id, user_id }) => {
    const isOnline = await redisData.sismember(`conversation:${conversation_id}:online`, user_id);
    const onlineUsers = await redisData.smembers(`conversation:${conversation_id}:online`);
    socket.emit('user_status', {
      user_id,
      online: !!isOnline,
      online_users: onlineUsers
    });
  });

  // Thêm sự kiện mark_as_read
  socket.on('mark_as_read', async (conversationId) => {
    try {
      const sessionCookie = socket.handshake.headers.cookie;
      // Gọi API Laravel để cập nhật is_read
      await axios.get(
        `${hostOriginPort}/conversation-user/mark-read/${conversationId}`,
        {
          headers: { Cookie: sessionCookie },
          withCredentials: true,
        }
      );
      // Thông báo đến các client trong conversation
      io.to(conversationId).emit('messages_read', { conversation_id: conversationId });
      console.log(`Messages marked as read for conversation ${conversationId}`);
    } catch (err) {
      console.error('Mark as read failed:', err.message);
      socket.emit('error', 'Failed to mark messages as read');
    }
  });

  socket.on('disconnect', async () => {
    if (conversationId) {
      await redisData.srem(`conversation:${conversationId}:online`, socket.user.user_id);
      const onlineUsers = await redisData.smembers(`conversation:${conversationId}:online`);
      io.to(conversationId).emit('user_status', {
        user_id: socket.user.user_id,
        online: false,
        online_users: onlineUsers
      });
    }
    console.log('User disconnected:', socket.id);
  });
});

// Lắng nghe Redis Stream
async function listenToStream() {
  const streamKey = 'chat_messages';
  let lastId = '$';

  while (true) {
    try {
      const messages = await redisStream.xread('BLOCK', 0, 'STREAMS', streamKey, lastId);
      if (messages) {
        for (const [stream, entries] of messages) {
          for (const [id, fields] of entries) {
            const message = {};
            for (let i = 0; i < fields.length; i += 2) {
              message[fields[i]] = fields[i + 1];
            }
            const onlineUsers = await redisData.smembers(`conversation:${message.conversation_id}:online`);
            io.to(message.conversation_id).emit('new_message', {
              ...message,
              online_users: onlineUsers
            });
            lastId = id;
          }
        }
      }
    } catch (error) {
      console.error('Error reading stream:', error);
    }
  }
}

listenToStream().catch(console.error);

server.listen(3000, hostOrigin, () => {
  console.log(`Socket.IO server running on ${hostOriginPort}`);
});
