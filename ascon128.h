/* ============================================================
 *  ascon128.h - ASCON-128 AEAD Official Reference (FIXED b=6)
 * ============================================================
 */
#ifndef ASCON128_H
#define ASCON128_H

#include <stdint.h>
#include <string.h>

#define ASCON_128_KEY_LEN   16
#define ASCON_128_NONCE_LEN 16
#define ASCON_128_TAG_LEN   16
#define ASCON_128_RATE      8

static inline uint64_t ROR(uint64_t x, int n) {
    return (x >> n) | (x << (64 - n));
}

static inline uint64_t LOAD64(const uint8_t *p) {
    return ((uint64_t)p[0] << 56) | ((uint64_t)p[1] << 48) |
           ((uint64_t)p[2] << 40) | ((uint64_t)p[3] << 32) |
           ((uint64_t)p[4] << 24) | ((uint64_t)p[5] << 16) |
           ((uint64_t)p[6] << 8)  | (uint64_t)p[7];
}

static inline void STORE64(uint8_t *p, uint64_t x) {
    p[0] = (uint8_t)(x >> 56); p[1] = (uint8_t)(x >> 48);
    p[2] = (uint8_t)(x >> 40); p[3] = (uint8_t)(x >> 32);
    p[4] = (uint8_t)(x >> 24); p[5] = (uint8_t)(x >> 16);
    p[6] = (uint8_t)(x >> 8);  p[7] = (uint8_t)(x);
}

static const uint8_t RC[12] = {
    0xf0, 0xe1, 0xd2, 0xc3, 0xb4, 0xa5, 0x96, 0x87, 0x78, 0x69, 0x5a, 0x4b
};

static inline void ROUND(uint64_t *x, uint8_t rc) {
    uint64_t t0, t1, t2, t3, t4;
    x[2] ^= rc;
    x[0] ^= x[4]; x[4] ^= x[3]; x[2] ^= x[1];
    t0 = x[0]; t1 = x[1]; t2 = x[2]; t3 = x[3]; t4 = x[4];
    t0 = ~t0; t1 = ~t1; t2 = ~t2; t3 = ~t3; t4 = ~t4;
    t0 &= x[1]; t1 &= x[2]; t2 &= x[3]; t3 &= x[4]; t4 &= x[0];
    x[0] ^= t1; x[1] ^= t2; x[2] ^= t3; x[3] ^= t4; x[4] ^= t0;
    x[1] ^= x[0]; x[0] ^= x[4]; x[3] ^= x[2]; x[2] = ~x[2];
    x[0] ^= ROR(x[0], 19) ^ ROR(x[0], 28);
    x[1] ^= ROR(x[1], 61) ^ ROR(x[1], 39);
    x[2] ^= ROR(x[2], 1)  ^ ROR(x[2], 6);
    x[3] ^= ROR(x[3], 10) ^ ROR(x[3], 17);
    x[4] ^= ROR(x[4], 7)  ^ ROR(x[4], 41);
}

static void P(uint64_t *x, int rounds) {
    for (int i = 12 - rounds; i < 12; i++) {
        ROUND(x, RC[i]);
    }
}

static int ascon_aead_encrypt(
    uint8_t *c,
    const uint8_t *m, size_t mlen,
    const uint8_t *ad, size_t adlen,
    const uint8_t *npub,
    const uint8_t *k
) {
    uint64_t x[5];
    uint8_t t[8] = {0};
    uint64_t K0 = LOAD64(k);
    uint64_t K1 = LOAD64(k + 8);
    uint64_t N0 = LOAD64(npub);
    uint64_t N1 = LOAD64(npub + 8);

    /* 1. Initialization (a = 12 rounds) */
    x[0] = 0x80400c0600000000ULL;
    x[1] = K0; x[2] = K1; x[3] = N0; x[4] = N1;
    P(x, 12);
    x[3] ^= K0; x[4] ^= K1;

    /* 2. Process Associated Data (b = 6 rounds untuk ASCON-128) */
    if (adlen > 0) {
        while (adlen >= ASCON_128_RATE) {
            x[0] ^= LOAD64(ad);
            P(x, 6);
            ad += ASCON_128_RATE;
            adlen -= ASCON_128_RATE;
        }
        memset(t, 0, 8);
        memcpy(t, ad, adlen);
        t[adlen] = 0x80;
        x[0] ^= LOAD64(t);
        P(x, 6);
    }
    x[4] ^= 1ULL;

    /* 3. Process Plaintext (b = 6 rounds untuk ASCON-128) */
    size_t i = 0;
    while (i + ASCON_128_RATE <= mlen) {
        uint64_t m_block = LOAD64(m + i);
        x[0] ^= m_block;
        STORE64(c + i, x[0]);
        P(x, 6);
        i += ASCON_128_RATE;
    }

    memset(t, 0, 8);
    size_t rem = mlen - i;
    if (rem > 0) {
        memcpy(t, m + i, rem);
    }
    t[rem] = 0x80;
    x[0] ^= LOAD64(t);

    if (rem > 0) {
        uint8_t out[8];
        STORE64(out, x[0]);
        memcpy(c + i, out, rem);
    }

    /* 4. Finalization (a = 12 rounds) */
    x[1] ^= K0; x[2] ^= K1;
    P(x, 12);
    x[3] ^= K0; x[4] ^= K1;

    /* 5. Store Tag tepat di ujung ciphertext */
    STORE64(c + mlen, x[3]);
    STORE64(c + mlen + 8, x[4]);

    return 0;
}

#endif /* ASCON128_H */