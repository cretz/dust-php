///<reference path="common.ts" />

module PHPUnit.Framework {
    //NOTE: this is NOT a full representation, just what I need

    class TestCase implements Pct.OldStyleNamespace {
        assertEquals(expected: any, actual: any, message?: string);
        assertTrue(condition: bool, message?: string);

        setExpectedException(exceptionName: string, exceptionMessage?: string, exceptionCode?: number);
    }
}