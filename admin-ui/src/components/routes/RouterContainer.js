import React from 'react';
import { Navigate, Route, Routes } from "react-router-dom";

const Settings = React.lazy(() => import(/* webpackChunkName: "Settings" */"../../pages/Settings"));

const RouterContainer = () => {
    return (
        <div
            className={`wlmi-flex wlmi-flex-col wlmi-w-full wlmi-min-h-[690px] wlmi-justify-start wlmi-px-8 wlmi-py-6 wlmi-rounded-xl wlmi-bg-white wlmi-border  wlmi-border-light_border wlmi-gap-6`}
        >
            <Routes>
                <Route
                    path={"/settings"}
                    element={
                        <React.Suspense fallback={<div />}>
                            <Settings />
                        </React.Suspense>
                    }
                />



                <Route path="/" element={<Navigate to={"/settings"} replace />} />
            </Routes>
        </div>
    );
};

export default RouterContainer;