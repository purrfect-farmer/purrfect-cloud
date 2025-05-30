import "dotenv/config";

import Fastify from "fastify";
import { Api } from "telegram";

import GramClient from "./lib/GramClient.js";

/** Fastify */
const fastify = Fastify({
    logger: true,
});

fastify
    .addHook("onRequest", async (request, reply) => {
        if (request.headers["x-api-key"] !== process.env.GRAMJS_API_KEY) {
            reply.code(401).send({ error: "Unauthorized" });
        }
    })
    .get("/sessions", async () => {
        return await GramClient.getSessions();
    })
    .post(
        "/exists",
        {
            schema: {
                body: {
                    type: "object",
                    required: ["session"],
                    properties: {
                        session: { type: "string" },
                    },
                },
            },
        },
        async (request) => {
            const result = await GramClient.sessionExists(request.body.session);

            return { result };
        }
    )
    .register((instance) => {
        instance
            .addHook("preHandler", async function (request, reply) {
                if (typeof request.body !== "object") {
                    return reply.code(400).send({ error: "Missing body!" });
                }

                /** Destructure */
                const { session } = request.body;

                if (!session) {
                    return reply
                        .code(400)
                        .send({ error: "Session is required!" });
                }

                try {
                    /** Initiate Client */
                    request.client = await GramClient.create(session);

                    /** Connect */
                    if (!request.client.connected) {
                        await request.client.connect();
                    }
                } catch (error) {
                    return reply
                        .code(500)
                        .send({ error: "Failed to get client!" });
                }
            })
            .post("/self", async (request) => {
                const result = await request.client.getSelf();
                return { result };
            })
            .post(
                "/phone",
                {
                    schema: {
                        body: {
                            type: "object",
                            required: ["phone"],
                            properties: {
                                phone: { type: "string" },
                            },
                        },
                    },
                },
                async function (request) {
                    /** Start Pending */
                    await request.client.startPending();

                    /** Send Phone Number */
                    const result = await request.client.startResponse(
                        "phone",
                        request.body.phone
                    );

                    /** Return Response */
                    return { result };
                }
            )
            .post(
                "/code",
                {
                    schema: {
                        body: {
                            type: "object",
                            required: ["code"],
                            properties: {
                                code: { type: "string" },
                            },
                        },
                    },
                },
                async function (request) {
                    /** Send Phone Code */
                    const result = await request.client.startResponse(
                        "code",
                        request.body.code
                    );

                    return { result };
                }
            )
            .post(
                "/password",
                {
                    schema: {
                        body: {
                            type: "object",
                            required: ["password"],
                            properties: {
                                password: { type: "string" },
                            },
                        },
                    },
                },
                async function (request) {
                    /** Send Password */
                    const result = await request.client.startResponse(
                        "password",
                        request.body.password
                    );

                    return { result };
                }
            )

            .post(
                "/webview",
                {
                    schema: {
                        body: {
                            type: "object",
                            required: ["bot", "shortName", "startParam"],
                            properties: {
                                bot: { type: "string" },
                                shortName: { type: "string" },
                                startParam: { type: "string" },
                            },
                        },
                    },
                },
                async function (request) {
                    /** Theme Params */
                    const themeParams = new Api.DataJSON({
                        data: JSON.stringify({
                            bg_color: "#ffffff",
                            text_color: "#000000",
                            hint_color: "#aaaaaa",
                            link_color: "#006aff",
                            button_color: "#2cab37",
                            button_text_color: "#ffffff",
                        }),
                    });

                    /** Get WebView */
                    const result = await request.client.invoke(
                        request.body.shortName
                            ? new Api.messages.RequestAppWebView({
                                  themeParams,
                                  platform: "android",
                                  peer: request.body.bot,
                                  startParam: request.body.startParam,
                                  app: new Api.InputBotAppShortName({
                                      botId: await request.client.getInputEntity(
                                          request.body.bot
                                      ),
                                      shortName: request.body.shortName,
                                  }),
                              })
                            : new Api.messages.RequestMainWebView({
                                  themeParams,
                                  platform: "android",
                                  bot: request.body.bot,
                                  peer: request.body.bot,
                                  startParam: request.body.startParam,
                              })
                    );

                    /** Destroy */
                    await request.client.destroy();

                    return { result };
                }
            )

            .post("/logout", async function (request) {
                /** Logout */
                await request.client.logout();

                return {
                    result: true,
                };
            });
    });

try {
    await fastify.listen({ port: 6767 });
} catch (error) {
    fastify.log.error(error);
    process.exit(1);
}
