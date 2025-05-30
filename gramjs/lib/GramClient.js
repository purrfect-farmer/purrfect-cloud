import fs from "node:fs/promises";
import path from "node:path";
import { Api, TelegramClient } from "telegram";
import { StringSession } from "telegram/sessions/index.js";
import { fileURLToPath } from "node:url";
import { globby } from "globby";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const config = {
    apiId: 2496,
    apiHash: "8da85b0d5bfe62527e5b244c209159c3",
    appVersion: "2.2 K",
    deviceModel:
        "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36",
    systemVersion: "Linux x86_64",
    systemLangCode: "en-US",
    langCode: "en",
};

export default class GramClient extends TelegramClient {
    /**
     * @type {Map<string, GramClient>}
     */
    static instances = new Map();

    /** Constructor */
    constructor(name, session, sessionFilePath, sessionFileExists) {
        super(session, config.apiId, config.apiHash, {
            connectionRetries: 5,
            appVersion: config.appVersion,
            deviceModel: config.deviceModel,
            systemVersion: config.systemVersion,
            systemLangCode: config.systemLangCode,
            langCode: config.langCode,
        });

        /** Store Name */
        this._name = name;

        /** Store File Path */
        this._sessionFilePath = sessionFilePath;

        /** Store Session File State */
        this._sessionFileExists = sessionFileExists;

        /** Initial Start Stage */
        this._resetStartStage();
    }

    /** Start Handler */
    _createStartHandler(stage) {
        return () =>
            new Promise((resolve) => {
                /** Resolve Stage Promise */
                this._startStagePromise?.resolve?.({
                    stage,
                });

                /** Remove Previous Handler */
                if (this._startStage) {
                    delete this._startHandlers[this._startStage];
                }

                /** Set Stage */
                this._startStage = stage;

                /** Set Handler */
                this._startHandlers[stage] = (data) => {
                    resolve(data);

                    /** Return new promise for the next stage */
                    return new Promise((_resolve, _reject) => {
                        this._startStagePromise = {
                            resolve: _resolve,
                            reject: _reject,
                        };
                    });
                };
            });
    }

    _resetStartStage() {
        /** Reset Start Stage */
        this._startStage = null;

        /** Reset Start Stage Promise */
        this._startStagePromise = null;

        /** Reset Start Handlers */
        this._startHandlers = {
            phone: null,
            code: null,
            password: null,
        };
    }

    /** Start Response */
    async startResponse(stage, response) {
        if (this._startStage !== stage) {
            throw new Error("Invalid stage!");
        } else if (!this._startHandlers[stage]) {
            throw new Error("Missing stage handler!");
        } else {
            return await this._startHandlers[stage](response);
        }
    }

    /** Start Pending */
    startPending() {
        if (this._startPromise) {
            return;
        }

        return new Promise((_resolve, _reject) => {
            /** Reset Start Stage */
            this._resetStartStage();

            /** Reset Start Stage Promise */
            this._startStagePromise = { resolve: _resolve, reject: _reject };

            /** Store Global Start Promise */
            this._startPromise = this.start({
                phoneNumber: this._createStartHandler("phone"),
                phoneCode: this._createStartHandler("code"),
                password: this._createStartHandler("password"),
                onError: (error) => {
                    if (this._startStagePromise) {
                        this._startStagePromise.reject(error);
                    } else {
                        console.error(
                            "Error occurred before handler was initialized:",
                            error
                        );
                    }
                },
            }).then(async () => {
                await this.saveSession();
                await this._startStagePromise?.resolve?.({
                    stage: "authenticated",
                    user: await this.getMe(),
                });
                await this.disconnect();
                this._resetStartStage();
            });
        });
    }

    /** Get Self */
    async getSelf() {
        try {
            return await this.getMe();
        } catch (e) {
            console.error(e);
            return null;
        }
    }

    /** Logout */
    async logout() {
        try {
            /** Try to reconnect */
            if (this.disconnected) {
                await this.connect();
            }

            /** Logout */
            await this.invoke(new Api.auth.LogOut({}));

            /** Destroy */
            await this.destroy();
        } catch (e) {
            /** Logout */
            console.error(e);
        } finally {
            /** Delete Session */
            await this.deleteSession();

            /** Remove Instance */
            await GramClient.delete(this._name);
        }
    }

    /** Save Session */
    async saveSession() {
        /** Write to File */
        await fs.writeFile(
            this._sessionFilePath,
            JSON.stringify(this.session.save())
        );

        /** Mark as Saved */
        this._sessionFileExists = true;
    }

    /** Delete Session */
    async deleteSession() {
        /** Delete File */
        if (this._sessionFileExists) {
            await fs.unlink(this._sessionFilePath);
        }

        /** Mark as Removed */
        this._sessionFileExists = false;
    }

    /**
     * Starts a Client
     * @param {string} name
     * @returns {GramClient}
     */
    static async create(name) {
        if (this.instances.has(name)) return this.instances.get(name);

        const sessionFilePath = await GramClient.getSessionPath(name);
        const sessionFileExists = await GramClient.sessionFileExists(name);

        const sessionData = sessionFileExists
            ? JSON.parse(await fs.readFile(sessionFilePath))
            : "";

        const stringSession = new StringSession(sessionData);

        return this.instances
            .set(
                name,
                new GramClient(
                    name,
                    stringSession,
                    sessionFilePath,
                    sessionFileExists
                )
            )
            .get(name);
    }

    static async getSessions() {
        const entries = await globby([
            GramClient.getStoragePath(),
            "!.gitignore",
        ]);
        const sessions = entries.map(
            (item) => path.basename(item, ".json").split("_")[1]
        );

        return sessions;
    }

    static async sessionExists(name) {
        return (
            GramClient.instances.has(name) || GramClient.sessionFileExists(name)
        );
    }

    static async sessionFileExists(name) {
        return await fs
            .access(GramClient.getSessionPath(name))
            .then(() => true)
            .catch(() => false);
    }

    static getSessionPath(name) {
        return path.join(GramClient.getStoragePath(), `session_${name}.json`);
    }

    static getStoragePath() {
        return path.resolve(__dirname, "../sessions");
    }

    /** Delete Instance */
    static delete(name) {
        this.instances.delete(name);
    }
}
