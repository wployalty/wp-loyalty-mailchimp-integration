import React from 'react';
import { Navigate, Route, Routes } from "react-router-dom";

const Settings = React.lazy(() => import(/* webpackChunkName: "Settings" */"../../pages/Settings"));

const RouterContainer = () => {
    return (
        <div
            className={`flex flex-col w-full min-h-[690px] justify-start px-8 py-6 rounded-xl bg-white border  border-light_border gap-6`}
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